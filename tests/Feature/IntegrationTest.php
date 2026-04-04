<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;

class IntegrationTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $admin;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();

    $adminRole = \App\Models\Role::where('role_name', 'admin')->first();
    $this->admin = User::factory()->create([
      'role_id' => $adminRole->id
    ]);

    // Ensure queue is not actually processed in tests
    Queue::fake();
    Mail::fake();
  }

  #[Test]
  public function complete_booking_flow_with_payment_integration()
  {
    // Step 1: Create booking
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingResponse->assertStatus(201);
    $bookingId = $bookingResponse->json('data.booking.id');

    // Step 2: Create payment for booking
    $paymentData = [
      'booking_id' => $bookingId,
      'payment_method' => 'bank_transfer'
    ];

    $paymentResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData);

    $paymentResponse->assertStatus(201);
    $paymentId = $paymentResponse->json('data.payment.id');

    // Step 3: Confirm payment
    $confirmData = [
      'transaction_id' => 'TXN123456',
      'payment_date' => now()->toDateTimeString(),
      'notes' => 'Payment confirmed via bank transfer'
    ];

    $confirmResponse = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/payments/{$paymentId}/confirm", $confirmData);

    $confirmResponse->assertStatus(200);

    // Step 4: Admin approves booking
    $approveResponse = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/approve");

    $approveResponse->assertStatus(200);

    // Verify final state
    $booking = Booking::find($bookingId);
    $payment = Payment::find($paymentId);

    $this->assertEquals(2, $booking->status_id); // approved
    $this->assertEquals(2, $payment->payment_status_id); // paid
  }

  #[Test]
  public function booking_with_promo_and_payment_integration()
  {
    // Create promo
    $promo = Promo::create([
      'promo_code' => 'INTEGRATION10',
      'description' => 'Integration test promo',
      'discount_type' => 'percentage',
      'discount_value' => 10,
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => true
    ]);

    // Step 1: Create booking with promo
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'INTEGRATION10'
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingResponse->assertStatus(201);
    $bookingId = $bookingResponse->json('data.booking.id');

    $booking = Booking::find($bookingId);
    $this->assertEquals('INTEGRATION10', $booking->promo_code);
    $this->assertGreaterThan(0, $booking->discount_amount);

    // Step 2: Create payment
    $paymentData = [
      'booking_id' => $bookingId,
      'payment_method' => 'bank_transfer'
    ];

    $paymentResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData);

    $paymentResponse->assertStatus(201);
    $paymentId = $paymentResponse->json('data.payment.id');

    $payment = Payment::find($paymentId);
    $this->assertEquals($booking->final_price, $payment->amount);

    // Step 3: Confirm payment
    $confirmData = [
      'transaction_id' => 'TXN789012',
      'payment_date' => now()->toDateTimeString()
    ];

    $confirmResponse = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/payments/{$paymentId}/confirm", $confirmData);

    $confirmResponse->assertStatus(200);

    // Verify promo usage incremented
    $promo->refresh();
    $this->assertEquals(1, $promo->used_count);
  }

  #[Test]
  public function booking_cancellation_with_payment_refund_flow()
  {
    // Step 1: Create and complete booking flow
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');

    $paymentData = [
      'booking_id' => $bookingId,
      'payment_method' => 'bank_transfer'
    ];

    $paymentResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData);

    $paymentId = $paymentResponse->json('data.payment.id');

    $confirmData = [
      'transaction_id' => 'TXN_CANCEL_TEST',
      'payment_date' => now()->toDateTimeString()
    ];

    $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/payments/{$paymentId}/confirm", $confirmData);

    // Step 2: Cancel booking
    $cancelResponse = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/bookings/{$bookingId}/cancel");

    $cancelResponse->assertStatus(200);

    // Verify booking and payment status
    $booking = Booking::find($bookingId);
    $payment = Payment::find($paymentId);

    $this->assertEquals(4, $booking->status_id); // cancelled
    $this->assertEquals(4, $payment->payment_status_id); // cancelled
  }

  #[Test]
  public function event_driven_notification_system_integration()
  {
    // Step 1: Create booking
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');

    // Step 2: Admin approves booking (should trigger notification)
    $approveResponse = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/approve");

    $approveResponse->assertStatus(200);

    // Verify notification was created
    $notification = Notification::where('user_id', $this->user->id)
      ->where('type', 'booking_approved')
      ->first();

    $this->assertNotNull($notification);
    $this->assertEquals($bookingId, $notification->booking_id);
  }

  #[Test]
  public function booking_expiration_flow_integration()
  {
    // Create booking that will expire
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');
    $booking = Booking::find($bookingId);

    // Manually expire the booking (simulate scheduled command)
    $booking->update([
      'expired_at' => now()->subMinutes(1),
      'status_id' => 5 // expired
    ]);

    // Verify booking is expired
    $booking->refresh();
    $this->assertEquals(5, $booking->status_id);

    // Check if notification was created
    $notification = Notification::where('user_id', $this->user->id)
      ->where('type', 'booking_expired')
      ->first();

    $this->assertNotNull($notification);
  }

  #[Test]
  public function payment_expiration_flow_integration()
  {
    // Create booking and payment
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');

    $paymentData = [
      'booking_id' => $bookingId,
      'payment_method' => 'bank_transfer'
    ];

    $paymentResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $paymentData);

    $paymentId = $paymentResponse->json('data.payment.id');
    $payment = Payment::find($paymentId);

    // Manually expire the payment
    $payment->update([
      'expired_at' => now()->subMinutes(1),
      'payment_status_id' => 5 // expired
    ]);

    // Verify payment is expired
    $payment->refresh();
    $this->assertEquals(5, $payment->payment_status_id);

    // Check if notification was created
    $notification = Notification::where('user_id', $this->user->id)
      ->where('type', 'payment_expired')
      ->first();

    $this->assertNotNull($notification);
  }

  #[Test]
  public function admin_booking_management_integration()
  {
    // User creates booking
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');

    // Admin views all bookings
    $adminBookingsResponse = $this->actingAs($this->admin, 'sanctum')
      ->getJson('/api/v1/admin/bookings');

    $adminBookingsResponse->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'bookings' => [
            '*' => [
              'id',
              'user',
              'court',
              'status',
              'total_price'
            ]
          ]
        ]
      ]);

    // Admin approves booking
    $approveResponse = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/approve");

    $approveResponse->assertStatus(200);

    // Admin finishes booking
    $finishResponse = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/finish");

    $finishResponse->assertStatus(200);

    // Verify final status
    $booking = Booking::find($bookingId);
    $this->assertEquals(3, $booking->status_id); // finished
  }

  #[Test]
  public function user_dashboard_integration()
  {
    // Create multiple bookings and payments for user
    for ($i = 0; $i < 3; $i++) {
      $bookingData = [
        'court_id' => 1,
        'booking_date' => now()->addDays($i + 1)->toDateString(),
        'slot_ids' => [1]
      ];

      $bookingResponse = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/bookings', $bookingData);

      $bookingId = $bookingResponse->json('data.booking.id');

      // Create payment for each booking
      $paymentData = [
        'booking_id' => $bookingId,
        'payment_method' => 'bank_transfer'
      ];

      $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/payments', $paymentData);
    }

    // User views their dashboard/profile
    $profileResponse = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/me');

    $profileResponse->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'user' => [
            'id',
            'name',
            'email'
          ]
        ]
      ]);

    // User views their bookings
    $bookingsResponse = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/bookings');

    $bookingsResponse->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'bookings' => [
            'data'
          ]
        ]
      ]);

    $bookings = $bookingsResponse->json('data.bookings.data');
    $this->assertCount(3, $bookings);

    // User views their payments
    $paymentsResponse = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/payments');

    $paymentsResponse->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'payments' => [
            'data'
          ]
        ]
      ]);

    $payments = $paymentsResponse->json('data.payments.data');
    $this->assertCount(3, $payments);
  }

  #[Test]
  public function notification_system_integration()
  {
    // Create booking
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingId = $bookingResponse->json('data.booking.id');

    // Trigger multiple events
    $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/approve");

    $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$bookingId}/finish");

    // User views notifications
    $notificationsResponse = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/notifications');

    $notificationsResponse->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'notifications' => [
            'data'
          ]
        ]
      ]);

    $notifications = $notificationsResponse->json('data.notifications.data');
    $this->assertNotEmpty($notifications);

    // Mark notifications as read
    if (!empty($notifications)) {
      $notificationId = $notifications[0]['id'];
      $markReadResponse = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/notifications/{$notificationId}/read");

      $markReadResponse->assertStatus(200);
    }
  }

  #[Test]
  public function availability_and_booking_integration()
  {
    $bookingDate = now()->addDays(1)->toDateString();
    $courtId = 1;

    // Check initial availability
    $availabilityResponse = $this->getJson("/api/v1/availability?date={$bookingDate}&court_id={$courtId}");
    $availabilityResponse->assertStatus(200);

    $slots = $availabilityResponse->json('data.slots');
    $this->assertNotEmpty($slots);

    // Find an available slot
    $availableSlot = null;
    foreach ($slots as $slot) {
      if ($slot['is_available']) {
        $availableSlot = $slot;
        break;
      }
    }

    $this->assertNotNull($availableSlot);

    // Book the available slot
    $bookingData = [
      'court_id' => $courtId,
      'booking_date' => $bookingDate,
      'slot_ids' => [$availableSlot['id']]
    ];

    $bookingResponse = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $bookingResponse->assertStatus(201);

    // Check availability again - slot should now be unavailable
    $availabilityResponse2 = $this->getJson("/api/v1/availability?date={$bookingDate}&court_id={$courtId}");
    $availabilityResponse2->assertStatus(200);

    $slotsAfter = $availabilityResponse2->json('data.slots');
    $slotAfter = collect($slotsAfter)->firstWhere('id', $availableSlot['id']);

    $this->assertFalse($slotAfter['is_available']);
  }
}
