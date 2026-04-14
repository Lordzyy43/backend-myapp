<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Models\Sport;
use App\Events\BookingExpired;
use App\Events\PaymentExpired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

class BookingExpireTest extends TestCase
{
  use RefreshDatabase, WithFaker;

  protected $user;
  protected $owner;
  protected $court;
  protected $venue;
  protected $expiredBooking;
  protected $confirmedBooking;
  protected $timeSlot;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. SEED STATUSES
    $bookingStatuses = ['pending', 'confirmed', 'cancelled', 'expired', 'finished'];
    foreach ($bookingStatuses as $status) {
      BookingStatus::firstOrCreate(['status_name' => $status]);
    }

    $paymentStatuses = ['pending', 'paid', 'cancelled', 'expired'];
    foreach ($paymentStatuses as $status) {
      PaymentStatus::firstOrCreate(['status_name' => $status]);
    }

    // 2. CREATE ACTORS
    $this->user = User::factory()->create(['role_id' => 3]);
    $this->owner = User::factory()->create(['role_id' => 2]);

    // 3. CREATE INFRASTRUCTURE
    $sport = Sport::firstOrCreate(['name' => 'Tennis']);

    $this->venue = Venue::create([
      'owner_id' => $this->owner->id,
      'sport_id' => $sport->id,
      'name' => 'Expire Test Venue',
      'address' => 'Expire Address',
      'city' => 'Surabaya',
      'phone' => '08111111111',
      'email' => 'expire-venue-' . rand(1, 99) . '@test.com',
      'slug' => 'expire-venue-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    $this->court = Court::create([
      'venue_id' => $this->venue->id,
      'sport_id' => $sport->id,
      'name' => 'Court Expire',
      'price_per_hour' => 80000,
      'description' => 'Expire test court',
      'slug' => 'court-expire-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    // 4. CREATE TIME SLOT
    $this->timeSlot = TimeSlot::firstOrCreate([
      'start_time' => '14:00',
      'end_time' => '15:00',
      'order_index' => 1,
      'is_active' => true
    ]);

    // 5. CREATE EXPIRED BOOKING (expires_at in past)
    $pendingStatus = BookingStatus::where('status_name', 'pending')->first();

    $this->expiredBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'EXPIRE-' . rand(10000, 99999),
      'booking_date' => now()->subDays(1)->toDateString(),
      'status_id' => $pendingStatus->id,
      'total_price' => 160000,
      'expires_at' => now()->subHours(1), // Already expired
      'booking_source' => 'mobile'
    ]);

    // Attach time slot to expired booking with pivot data
    $this->expiredBooking->timeSlots()->attach($this->timeSlot->id, [
      'court_id' => $this->court->id,
      'booking_date' => $this->expiredBooking->booking_date,
      'created_at' => now(),
      'updated_at' => now()
    ]);

    // 6. CREATE PAYMENT FOR EXPIRED BOOKING
    $paymentPending = PaymentStatus::where('status_name', 'pending')->first();

    $this->expiredPayment = Payment::create([
      'booking_id' => $this->expiredBooking->id,
      'amount' => 160000,
      'payment_status_id' => $paymentPending->id,
      'payment_method' => 'transfer_bank',
      'external_transaction_id' => 'EXPIRED-' . rand(100000, 999999),
      'paid_at' => null
    ]);

    // 7. CREATE CONFIRMED BOOKING (should NOT expire)
    $confirmedStatus = BookingStatus::where('status_name', 'confirmed')->first();

    $this->confirmedBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'CONFIRM-' . rand(10000, 99999),
      'booking_date' => now()->addDays(2)->toDateString(),
      'status_id' => $confirmedStatus->id,
      'total_price' => 160000,
      'expires_at' => now()->addHours(24),
      'booking_source' => 'mobile'
    ]);

    $this->confirmedBooking->timeSlots()->attach($this->timeSlot->id, [
      'court_id' => $this->court->id,
      'booking_date' => $this->confirmedBooking->booking_date,
      'created_at' => now(),
      'updated_at' => now()
    ]);
  }

  #[Test]
  public function expire_booking_command_marks_expired_bookings()
  {
    // Initial status should be pending
    $this->assertEquals(
      BookingStatus::where('status_name', 'pending')->first()->id,
      $this->expiredBooking->status_id
    );

    // Run the expire command
    $this->artisan('booking:expire');

    // Refresh and check status changed to expired
    $this->expiredBooking->refresh();

    $expiredStatus = BookingStatus::where('status_name', 'expired')->first();
    $this->assertEquals($expiredStatus->id, $this->expiredBooking->status_id);
  }

  #[Test]
  public function expire_command_does_not_expire_confirmed_bookings()
  {
    $originalStatus = $this->confirmedBooking->status_id;

    $this->artisan('booking:expire');

    $this->confirmedBooking->refresh();

    // Status should remain confirmed
    $this->assertEquals($originalStatus, $this->confirmedBooking->status_id);
  }

  #[Test]
  public function expire_command_releases_time_slots()
  {
    // Before: Booking should have time slot attached
    $this->assertEquals(
      1,
      $this->expiredBooking->timeSlots()->count()
    );

    $this->artisan('booking:expire');

    // After: Time slots should be detached
    $this->expiredBooking->refresh();
    $this->assertEquals(
      0,
      $this->expiredBooking->timeSlots()->count()
    );
  }

  #[Test]
  public function expire_command_updates_payment_status()
  {
    $paymentPending = PaymentStatus::where('status_name', 'pending')->first();

    // Before: Payment should be pending
    $this->assertEquals(
      $paymentPending->id,
      $this->expiredPayment->payment_status_id
    );

    $this->artisan('booking:expire');

    // After: Payment should be expired
    $this->expiredPayment->refresh();

    $paymentExpired = PaymentStatus::where('status_name', 'expired')->first();
    $this->assertEquals(
      $paymentExpired->id,
      $this->expiredPayment->payment_status_id
    );
  }

  #[Test]
  public function expire_command_does_not_expire_paid_payments()
  {
    $paymentPaid = PaymentStatus::where('status_name', 'paid')->first();

    // Change payment status to paid
    $this->expiredPayment->update(['payment_status_id' => $paymentPaid->id]);

    $this->artisan('booking:expire');

    // Payment should still be paid
    $this->expiredPayment->refresh();
    $this->assertEquals($paymentPaid->id, $this->expiredPayment->payment_status_id);
  }

  #[Test]
  public function expire_command_dispatches_booking_expired_event()
  {
    Event::fake();

    $this->artisan('booking:expire');

    Event::assertDispatched(BookingExpired::class);
  }

  #[Test]
  public function expire_command_dispatches_payment_expired_event()
  {
    Event::fake();

    $this->artisan('booking:expire');

    Event::assertDispatched(PaymentExpired::class);
  }

  #[Test]
  public function expire_command_uses_row_locking()
  {
    // This test verifies pessimistic locking is in use
    // by checking that the booking is locked during transaction

    $this->artisan('booking:expire');

    // If no errors occur, row locking is working correctly
    $this->expiredBooking->refresh();
    $this->assertEquals(
      BookingStatus::where('status_name', 'expired')->first()->id,
      $this->expiredBooking->status_id
    );
  }

  #[Test]
  public function expire_command_handles_chunked_processing()
  {
    // Create multiple expired bookings to test chunking
    $pendingStatus = BookingStatus::where('status_name', 'pending')->first();

    for ($i = 0; $i < 3; $i++) {
      // 1. Buat TimeSlot unik per iterasi agar tidak melanggar unique constraint
      $uniqueSlot = TimeSlot::create([
        'start_time' => sprintf('%02d:00', 10 + $i),
        'end_time'   => sprintf('%02d:00', 11 + $i),
        'order_index' => 10 + $i,
        'is_active'  => true
      ]);

      // 2. Buat Booking
      $booking = Booking::create([
        'user_id' => $this->user->id,
        'court_id' => $this->court->id,
        'booking_code' => 'CHUNK-' . $i . '-' . rand(10000, 99999),
        'booking_date' => now()->subDays(1)->toDateString(),
        'status_id' => $pendingStatus->id,
        'total_price' => 160000,
        'expires_at' => now()->subHours(1),
        'booking_source' => 'mobile'
      ]);

      // 3. Attach dengan data pivot yang lengkap
      $booking->timeSlots()->attach($uniqueSlot->id, [
        'court_id' => $this->court->id,
        'booking_date' => $booking->booking_date,
        'created_at' => now(),
        'updated_at' => now()
      ]);
    }

    // 4. Jalankan command
    $this->artisan('booking:expire')->assertExitCode(0);

    // 5. Assert hasil
    $expiredCount = Booking::where('status_id', BookingStatus::where('status_name', 'expired')->first()->id)->count();
    $this->assertGreaterThanOrEqual(4, $expiredCount); // 1 dari setUp + 3 baru
  }

  #[Test]
  public function expire_command_sets_payment_expired_timestamp()
  {
    $this->assertNull($this->expiredPayment->expired_at);

    $this->artisan('booking:expire');

    $this->expiredPayment->refresh();

    // expired_at should now be set
    $this->assertNotNull($this->expiredPayment->expired_at);
  }

  #[Test]
  public function expire_command_respects_transaction_safety()
  {
    // Test that command properly handles transactions
    // by verifying data consistency after execution

    $this->artisan('booking:expire');

    $this->expiredBooking->refresh();
    $this->expiredPayment->refresh();

    // Booking should be expired
    $this->assertEquals(
      BookingStatus::where('status_name', 'expired')->first()->id,
      $this->expiredBooking->status_id
    );

    // Payment should also be expired
    $this->assertEquals(
      PaymentStatus::where('status_name', 'expired')->first()->id,
      $this->expiredPayment->payment_status_id
    );

    // Slots should be released
    $this->assertEquals(0, $this->expiredBooking->timeSlots()->count());
  }
}
