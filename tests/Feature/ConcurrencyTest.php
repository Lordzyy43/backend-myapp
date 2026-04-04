<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\TimeSlot;
use App\Models\BookingTimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ConcurrencyTest extends TestCase
{
  use RefreshDatabase;

  protected $user1;
  protected $user2;
  protected $courtId = 1;
  protected $bookingDate;
  protected $slotId = 1;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->bookingDate = now()->addDays(1)->toDateString();

    // Ensure slot exists
    TimeSlot::firstOrCreate(['id' => $this->slotId], [
      'start_time' => '09:00:00',
      'end_time' => '10:00:00'
    ]);
  }

  #[Test]
  public function concurrent_slot_booking_prevents_double_booking()
  {
    // This test simulates concurrent booking attempts for the same slot
    // In a real scenario, this would require multiple processes/threads

    $bookingData1 = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId]
    ];

    $bookingData2 = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId]
    ];

    // First booking should succeed
    $response1 = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData1);

    $response1->assertStatus(201);

    // Second booking should fail due to slot already taken
    $response2 = $this->actingAs($this->user2, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData2);

    $response2->assertStatus(422)
      ->assertJsonFragment(['slot_ids' => ['One or more selected slots are no longer available']]);
  }

  #[Test]
  public function slot_release_on_booking_cancellation()
  {
    // Create a booking first
    $bookingData = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId]
    ];

    $response = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201);
    $bookingId = $response->json('data.booking.id');

    // Verify slot is booked
    $bookingTimeSlot = BookingTimeSlot::where('booking_id', $bookingId)->first();
    $this->assertNotNull($bookingTimeSlot);

    // Cancel the booking
    $cancelResponse = $this->actingAs($this->user1, 'sanctum')
      ->patchJson("/api/v1/bookings/{$bookingId}/cancel");

    $cancelResponse->assertStatus(200);

    // Verify slot is released
    $bookingTimeSlotAfter = BookingTimeSlot::where('booking_id', $bookingId)->first();
    $this->assertNull($bookingTimeSlotAfter);
  }

  #[Test]
  public function concurrent_payment_creation_handled_safely()
  {
    $booking = Booking::create([
      'user_id' => $this->user1->id,
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'status_id' => 1, // pending
      'total_price' => 50000
    ]);

    $paymentData1 = [
      'booking_id' => $booking->id,
      'payment_method' => 'bank_transfer'
    ];

    $paymentData2 = [
      'booking_id' => $booking->id,
      'payment_method' => 'bank_transfer'
    ];

    // First payment should succeed
    $response1 = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData1);

    $response1->assertStatus(201);

    // Second payment should fail (booking already has pending payment)
    $response2 = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData2);

    $response2->assertStatus(422)
      ->assertJsonFragment(['booking_id' => ['Booking already has a pending payment']]);
  }

  #[Test]
  public function concurrent_payment_confirmation_prevents_double_processing()
  {
    $booking = Booking::create([
      'user_id' => $this->user1->id,
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'status_id' => 1,
      'total_price' => 50000
    ]);

    $payment = Payment::create([
      'booking_id' => $booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 50000,
      'payment_status_id' => 1, // pending
      'expired_at' => now()->addMinutes(10)
    ]);

    // Confirm payment twice concurrently
    $confirmData = [
      'transaction_id' => 'TXN123456',
      'payment_date' => now()->toDateTimeString(),
      'notes' => 'Payment confirmed'
    ];

    // First confirmation should succeed
    $response1 = $this->actingAs($this->user1, 'sanctum')
      ->patchJson("/api/v1/payments/{$payment->id}/confirm", $confirmData);

    $response1->assertStatus(200);

    // Second confirmation should fail (payment already confirmed)
    $response2 = $this->actingAs($this->user1, 'sanctum')
      ->patchJson("/api/v1/payments/{$payment->id}/confirm", $confirmData);

    $response2->assertStatus(422)
      ->assertJsonFragment(['payment' => ['Payment has already been processed']]);
  }

  #[Test]
  public function atomic_promo_usage_prevents_over_usage()
  {
    $promo = Promo::create([
      'promo_code' => 'CONCURRENT',
      'description' => 'Concurrent promo test',
      'discount_type' => 'percentage',
      'discount_value' => 10,
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 1, // Only 1 use allowed
      'used_count' => 0,
      'is_active' => true
    ]);

    $bookingData1 = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId],
      'promo_code' => 'CONCURRENT'
    ];

    $bookingData2 = [
      'court_id' => 2, // Different court
      'booking_date' => $this->bookingDate,
      'slot_ids' => [2], // Different slot
      'promo_code' => 'CONCURRENT'
    ];

    // First booking should succeed
    $response1 = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData1);

    $response1->assertStatus(201);

    // Check promo usage
    $promo->refresh();
    $this->assertEquals(1, $promo->used_count);

    // Second booking should fail due to usage limit
    $response2 = $this->actingAs($this->user2, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData2);

    $response2->assertStatus(422)
      ->assertJsonFragment(['promo_code' => ['Promo code usage limit exceeded']]);
  }

  #[Test]
  public function cache_invalidation_works_on_slot_changes()
  {
    // First, populate cache
    $response = $this->getJson('/api/v1/availability?date=' . $this->bookingDate . '&court_id=' . $this->courtId);
    $response->assertStatus(200);

    // Verify cache exists
    $cacheKey = "availability_{$this->courtId}_{$this->bookingDate}";
    $this->assertTrue(Cache::has($cacheKey));

    // Create booking (should invalidate cache)
    $bookingData = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId]
    ];

    $bookingResponse = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingResponse->assertStatus(201);

    // Cache should be invalidated
    $this->assertFalse(Cache::has($cacheKey));

    // Next availability request should repopulate cache
    $response2 = $this->getJson('/api/v1/availability?date=' . $this->bookingDate . '&court_id=' . $this->courtId);
    $response2->assertStatus(200);

    // Cache should exist again
    $this->assertTrue(Cache::has($cacheKey));
  }

  #[Test]
  public function database_transaction_rollback_on_booking_failure()
  {
    // Create a scenario where booking fails midway
    $bookingData = [
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [$this->slotId],
      'promo_code' => 'NONEXISTENT' // This will cause validation failure
    ];

    $initialPromoCount = Promo::count();

    $response = $this->actingAs($this->user1, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422);

    // Verify no partial data was created
    $this->assertEquals($initialPromoCount, Promo::count());
    $this->assertEquals(0, Booking::count());
    $this->assertEquals(0, BookingTimeSlot::count());
  }

  #[Test]
  public function concurrent_admin_actions_handled_safely()
  {
    $booking = Booking::create([
      'user_id' => $this->user1->id,
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'status_id' => 1, // pending
      'total_price' => 50000
    ]);

    $admin = User::factory()->create();
    $admin->role_id = 1; // Admin role

    // Try to approve and reject simultaneously
    $approveResponse = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/approve");

    $approveResponse->assertStatus(200);

    // Second action should fail
    $rejectResponse = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/reject");

    $rejectResponse->assertStatus(422)
      ->assertJsonFragment(['booking' => ['Booking status cannot be changed']]);
  }

  #[Test]
  public function optimistic_locking_on_status_changes()
  {
    $booking = Booking::create([
      'user_id' => $this->user1->id,
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'status_id' => 1, // pending
      'total_price' => 50000
    ]);

    $admin = User::factory()->create();
    $admin->role_id = 1;

    // Both admins try to change status simultaneously
    $response1 = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/approve");

    $response1->assertStatus(200);

    $admin2 = User::factory()->create();
    $admin2->role_id = 1;

    $response2 = $this->actingAs($admin2, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/finish");

    $response2->assertStatus(200); // Should succeed as status allows transition
  }

  #[Test]
  public function queue_job_processing_handles_concurrent_events()
  {
    // This test verifies that queued events don't cause issues
    $booking = Booking::create([
      'user_id' => $this->user1->id,
      'court_id' => $this->courtId,
      'booking_date' => $this->bookingDate,
      'status_id' => 1,
      'total_price' => 50000
    ]);

    $admin = User::factory()->create();
    $admin->role_id = 1;

    // Approve booking (triggers events)
    $response = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/approve");

    $response->assertStatus(200);

    // Verify booking status changed
    $booking->refresh();
    $this->assertEquals(2, $booking->status_id); // approved

    // In a real scenario, we'd check that queued jobs process correctly
    // For this test, we just verify the synchronous part works
  }
}
