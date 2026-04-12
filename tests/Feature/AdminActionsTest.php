<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class AdminActionsTest extends TestCase
{
  use RefreshDatabase, WithFaker;

  protected $admin;
  protected $owner;
  protected $user;
  protected $court;
  protected $venue;
  protected $booking;
  protected $payment;
  protected $timeSlot;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. SEED ROLES (Wajib ada di paling atas agar relasi User berhasil)
    $adminRole = Role::firstOrCreate(['id' => 1], ['role_name' => 'admin']);
    $ownerRole = Role::firstOrCreate(['id' => 2], ['role_name' => 'owner']);
    $userRole  = Role::firstOrCreate(['id' => 3], ['role_name' => 'user']);

    // 2. SEED STATUSES
    $bookingStatuses = ['pending', 'confirmed', 'cancelled', 'expired', 'finished'];
    foreach ($bookingStatuses as $status) {
      BookingStatus::firstOrCreate(['status_name' => $status]);
    }

    $paymentStatuses = ['pending', 'paid', 'cancelled', 'expired'];
    foreach ($paymentStatuses as $status) {
      PaymentStatus::firstOrCreate(['status_name' => $status]);
    }

    // 3. CREATE ACTORS
    $adminRole = Role::where('role_name', 'admin')->first();
    $ownerRole = Role::where('role_name', 'owner')->first();
    $userRole  = Role::where('role_name', 'user')->first();

    $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    $this->admin->setRelation('role', $adminRole); // <--- INI KUNCI!

    $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $this->owner->setRelation('role', $ownerRole);

    $this->user  = User::factory()->create(['role_id' => $userRole->id]);
    $this->user->setRelation('role', $userRole);

    // 4. CREATE INFRASTRUCTURE
    $sport = Sport::firstOrCreate(['name' => 'Football']);

    $this->venue = Venue::create([
      'owner_id' => $this->owner->id,
      'sport_id' => $sport->id,
      'name' => 'Admin Test Venue',
      'address' => 'Admin Address',
      'city' => 'Bandung',
      'phone' => '08987654321',
      'email' => 'admin-venue-' . rand(1, 999) . '@test.com',
      'slug' => 'admin-venue-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    $this->court = Court::create([
      'venue_id' => $this->venue->id,
      'sport_id' => $sport->id,
      'name' => 'Court Admin',
      'price_per_hour' => 100000,
      'description' => 'Admin test court',
      'slug' => 'court-admin-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    // 5. CREATE TIME SLOTS
    $this->timeSlot = TimeSlot::firstOrCreate(
      ['start_time' => '10:00', 'end_time' => '11:00'],
      ['order_index' => 1, 'is_active' => true]
    );

    // 6. CREATE BOOKING
    $pendingStatus = BookingStatus::where('status_name', 'pending')->first();

    $this->booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'time_slot_id' => $this->timeSlot->id,
      'booking_code' => 'ADMIN-' . rand(10000, 99999),
      'booking_date' => now()->toDateString(),
      'status_id' => $pendingStatus->id,
      'total_price' => 200000,
      'expires_at' => now()->addHour(),
      'booking_source' => 'mobile'
    ]);

    $this->booking->timeSlots()->attach($this->timeSlot->id, [
      'court_id' => $this->court->id,
      'booking_date' => $this->booking->booking_date
    ]);

    // 7. CREATE PAYMENT
    $paymentPendingStatus = PaymentStatus::where('status_name', 'pending')->first();

    $this->payment = Payment::create([
      'booking_id' => $this->booking->id,
      'amount' => 200000,
      'payment_status_id' => $paymentPendingStatus->id,
      'payment_method' => 'transfer_bank',
      'external_transaction_id' => 'TRX-' . rand(100000, 999999),
      'paid_at' => null
    ]);
  }

  #[Test]
  public function admin_can_view_all_bookings()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson('/api/v1/admin/bookings');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'bookings' => [
            '*' => [
              'id',
              'booking_code',
              'user_id',
              'court_id',
              'status'
            ]
          ]
        ]
      ]);
  }

  #[Test]
  public function admin_can_approve_pending_booking()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/approve");

    $response->assertStatus(200)
      ->assertJson(['success' => true]);

    $this->booking->refresh();
    $this->assertEquals('confirmed', $this->booking->status->status_name);
  }

  #[Test]
  public function admin_can_reject_pending_booking()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/reject");

    $response->assertStatus(200)
      ->assertJson(['success' => true]);

    $this->booking->refresh();
    $this->assertEquals('cancelled', $this->booking->status->status_name);
  }

  #[Test]
  public function admin_can_finish_booking()
  {
    // 1. SET WAKTU SECARA SANGAT EKSPLISIT
    $targetTime = \Carbon\Carbon::create(2026, 4, 12, 13, 0, 0, 'Asia/Jakarta');
    \Carbon\Carbon::setTestNow($targetTime);
    try {
      // 2. Persiapan Data (Booking harus status 'confirmed')
      $confirmedStatus = BookingStatus::where('status_name', 'confirmed')->first();
      $this->booking->update(['status_id' => $confirmedStatus->id]);
      $this->booking->refresh();

      // 3. Request finish (PATCH)
      $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/finish");

      // 4. Assert response
      $response->assertStatus(200)
        ->assertJson(['success' => true]);

      // 5. Assert database
      $this->booking->refresh();
      $this->assertEquals('finished', $this->booking->status->status_name);
    } finally {
      // 6. WAJIB: Reset waktu agar tidak merusak tes lainnya
      \Carbon\Carbon::setTestNow();
    }
  }

  #[Test]
  public function admin_cannot_approve_already_confirmed_booking()
  {
    $confirmedStatus = BookingStatus::where('status_name', 'confirmed')->first();
    $this->booking->update(['status_id' => $confirmedStatus->id]);

    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/approve");

    // Should fail or return error
    $response->assertStatus(422);
  }

  #[Test]
  public function user_cannot_approve_booking()
  {
    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/approve");

    $response->assertStatus(403); // Forbidden
  }

  #[Test]
  public function admin_can_view_all_payments()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson('/api/v1/admin/payments');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          '*' => [
            'id',
            'booking_id',
            'amount',
            'payment_status_id'
          ]
        ]
      ]);
  }

  #[Test]
  public function admin_can_view_payment_detail()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson("/api/v1/admin/payments/{$this->payment->id}");

    $response->assertStatus(200)
      ->assertJson([
        'success' => true,
        'data' => [
          'id' => $this->payment->id,
          'booking_id' => $this->payment->booking_id
        ]
      ]);
  }

  #[Test]
  public function admin_can_approve_pending_payment()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$this->payment->id}/approve");

    $response->assertStatus(200)
      ->assertJson(['success' => true]);

    $this->payment->refresh();
    $this->assertEquals('paid', $this->payment->status->status_name);
  }

  #[Test]
  public function admin_can_reject_pending_payment()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$this->payment->id}/reject");

    $response->assertStatus(200)
      ->assertJson(['success' => true]);

    $this->payment->refresh();
    $this->assertEquals('failed', $this->payment->status->status_name);
  }

  #[Test]
  public function admin_cannot_approve_already_paid_payment()
  {
    // 1. Arrange: Ubah status menjadi 'paid'
    $paidStatus = PaymentStatus::where('status_name', 'paid')->first();
    $this->payment->update(['payment_status_id' => $paidStatus->id]);
    $this->payment->refresh();

    // 2. Act: Coba approve pembayaran yang sudah 'paid'
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$this->payment->id}/approve");

    // 3. Assert: Pastikan gagal dengan 400
    $response->assertStatus(400)
      ->assertJson(['success' => false]); // Pastikan response body sesuai
  }

  #[Test]
  public function user_cannot_approve_payment()
  {
    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$this->payment->id}/approve");

    $response->assertStatus(403); // Forbidden
  }

  #[Test]
  public function admin_can_view_booking_report()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson('/api/v1/admin/bookings/reports');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data'
      ]);
  }

  #[Test]
  public function admin_can_filter_payments_by_status()
  {
    $pendingStatus = PaymentStatus::where('status_name', 'pending')->first();

    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson("/api/v1/admin/payments?status_id={$pendingStatus->id}");

    $response->assertStatus(200)
      ->assertJson(['success' => true]);
  }

  #[Test]
  public function owner_cannot_access_admin_booking_actions()
  {
    $response = $this->actingAs($this->owner, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$this->booking->id}/approve");

    $response->assertStatus(403); // Forbidden
  }
}
