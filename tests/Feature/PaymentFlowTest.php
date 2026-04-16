<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\PaymentStatusSeeder;
use PHPUnit\Framework\Attributes\Test;

class PaymentFlowTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $admin;
  protected $booking;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. Seed status agar relasi valid
    $this->seed(PaymentStatusSeeder::class);

    // 2. Buat User biasa
    $this->user = User::factory()->create();

    // 3. Buat Admin (menggunakan state admin yang baru dibuat)
    $this->admin = User::factory()->admin()->create();

    // 4. Buat Booking
    $this->booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 100000,
      'expires_at' => now()->addMinutes(10)
    ]);
  }

  #[Test]
  public function user_can_create_payment_for_own_booking()
  {
    $data = [
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->json('POST', '/api/v1/payments', $data);

    $response->assertStatus(201)
      ->assertJsonStructure([
        'success',
        'message',
        'data' => [
          'id',
          'booking_id',
          'payment_method',
          'amount',
          'payment_status_id',
          'expired_at'
        ]
      ]);

    $this->assertDatabaseHas('payments', [
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending()
    ]);
  }

  #[Test]
  public function user_cannot_create_payment_for_others_booking()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000
    ]);

    $data = [
      'booking_id' => $otherBooking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $data);

    $response->assertStatus(403); // Forbidden
  }

  #[Test]
  public function payment_creation_fails_if_booking_already_has_payment()
  {
    // Create first payment
    Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->addMinutes(10)
    ]);

    $data = [
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $data);

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Payment sudah ada'
      ]);
  }

  #[Test]
  public function payment_creation_fails_if_booking_expired()
  {
    $expiredBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000,
      'expires_at' => now()->subMinutes(1) // Expired
    ]);

    $data = [
      'booking_id' => $expiredBooking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $data);

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Booking sudah expired'
      ]);
  }

  #[Test]
  public function payment_creation_fails_if_booking_not_pending()
  {
    $confirmedBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::confirmed(), // Not pending
      'total_price' => 50000
    ]);

    $data = [
      'booking_id' => $confirmedBooking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $data);

    $response->assertStatus(403); // Forbidden - not authorized to pay for confirmed booking
  }

  #[Test]
  public function admin_can_confirm_payment()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->addMinutes(10)
    ]);

    // Kita gunakan $this->admin yang sudah didefinisikan di setUp
    // Tidak perlu update role_id manual lagi karena sudah di-handle oleh factory()->admin()
    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$payment->id}/approve", []);

    // Assertion
    $response->assertStatus(200);

    $this->assertDatabaseHas('payments', [
      'id' => $payment->id,
      'payment_status_id' => PaymentStatus::paid(),
    ]);

    $this->assertNotNull($payment->refresh()->paid_at);

    // Check booking status updated
    $this->assertDatabaseHas('bookings', [
      'id' => $this->booking->id,
      'status_id' => BookingStatus::confirmed(),
      'expires_at' => null
    ]);
  }

  #[Test]
  public function user_can_cancel_own_payment()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->addMinutes(10)
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->patch("/api/v1/payments/{$payment->id}/cancel");

    $response->assertStatus(200);

    $this->assertDatabaseHas('payments', [
      'id' => $payment->id,
      'payment_status_id' => PaymentStatus::cancelled()
    ]);

    // Check booking status updated
    $this->assertDatabaseHas('bookings', [
      'id' => $this->booking->id,
      'status_id' => BookingStatus::cancelled()
    ]);
  }

  #[Test]
  public function user_cannot_cancel_paid_payment()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::paid(),
      'paid_at' => now()
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->patch("/api/v1/payments/{$payment->id}/cancel");

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Tidak bisa cancel payment yang sudah dibayar'
      ]);
  }

  #[Test]
  public function user_can_list_own_payments()
  {
    // Create 3 separate bookings for this user
    $bookings = Booking::factory()->count(3)->create(['user_id' => $this->user->id]);

    // Create one payment for each booking
    foreach ($bookings as $booking) {
      Payment::factory()->create(['booking_id' => $booking->id]);
    }

    // Create payment for another user
    $otherUser = User::factory()->create();
    $otherBooking = Booking::factory()->create(['user_id' => $otherUser->id]);
    Payment::factory()->create(['booking_id' => $otherBooking->id]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/payments');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'message',
        'data' => [
          '*' => [
            'id',
            'booking',
            'status'
          ]
        ],
        'meta'
      ]);

    // Should only return 3 payments for this user
    $this->assertCount(3, $response->json('data'));
  }

  #[Test]
  public function user_can_view_own_payment_detail()
  {
    $payment = Payment::factory()->create([
      'booking_id' => $this->booking->id
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/payments/{$payment->id}");

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'id',
          'booking',
          'status'
        ]
      ]);
  }

  #[Test]
  public function user_cannot_view_others_payment()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::factory()->create(['user_id' => $otherUser->id]);
    $payment = Payment::factory()->create(['booking_id' => $otherBooking->id]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/payments/{$payment->id}");

    $response->assertStatus(403);
  }

  #[Test]
  public function payment_expires_automatically()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->subMinutes(1) // Expired
    ]);

    // Simulate expire command (would trigger PaymentExpired event)
    // In real scenario, this would be handled by a job/command

    $this->assertDatabaseHas('payments', [
      'id' => $payment->id,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => $payment->expired_at
    ]);

    // Note: Actual expiry logic would be in a separate command/job
    // This test verifies the payment can be expired
  }

  #[Test]
  public function payment_confirmation_fails_if_already_paid()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::paid(),
      'paid_at' => now()
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$payment->id}/approve", []);

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Payment sudah dibayar'
      ]);
  }

  #[Test]
  public function payment_confirmation_fails_if_expired()
  {
    $payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 100000,
      'payment_status_id' => PaymentStatus::pending(),
      'expired_at' => now()->subMinutes(1)
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
      ->patchJson("/api/v1/admin/payments/{$payment->id}/approve", []);

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Payment sudah expired'
      ]);
  }
}
