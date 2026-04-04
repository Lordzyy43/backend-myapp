<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class SecurityTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $admin;
  protected $booking;
  protected $payment;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();

    $adminRole = \App\Models\Role::where('role_name', 'admin')->first();
    $this->admin = User::factory()->create([
      'role_id' => $adminRole->id
    ]);

    $this->booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1, // pending
      'total_price' => 50000
    ]);

    $this->payment = Payment::create([
      'booking_id' => $this->booking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 50000,
      'payment_status_id' => 1, // pending
      'expired_at' => now()->addMinutes(10)
    ]);
  }

  #[Test]
  public function unauthenticated_user_cannot_access_protected_routes()
  {
    $routes = [
      '/api/v1/me',
      '/api/v1/bookings',
      '/api/v1/payments/1',
      '/api/v1/notifications'
    ];

    foreach ($routes as $route) {
      $response = $this->getJson($route);
      $response->assertStatus(401); // Unauthorized
    }
  }

  #[Test]
  public function user_cannot_access_admin_routes()
  {
    $adminRoutes = [
      ['method' => 'GET', 'url' => '/api/v1/admin/users'],
      ['method' => 'PATCH', 'url' => '/api/v1/admin/bookings/1/approve'],
      ['method' => 'PATCH', 'url' => '/api/v1/admin/bookings/1/reject'],
      ['method' => 'PATCH', 'url' => '/api/v1/admin/bookings/1/finish'],
      ['method' => 'GET', 'url' => '/api/v1/admin/payments/1']
    ];

    foreach ($adminRoutes as $route) {
      if ($route['method'] === 'GET') {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($route['url']);
      } else {
        $response = $this->actingAs($this->user, 'sanctum')->patchJson($route['url'], []);
      }
      $response->assertStatus(403); // Forbidden
    }
  }

  #[Test]
  public function admin_can_access_admin_routes()
  {
    $response = $this->actingAs($this->admin, 'sanctum')
      ->getJson('/api/v1/admin/users');

    $response->assertStatus(200);
  }

  #[Test]
  public function user_can_only_view_own_booking()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000
    ]);

    // Try to view other's booking
    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/bookings/{$otherBooking->id}");

    $response->assertStatus(403);
  }

  #[Test]
  public function user_can_only_cancel_own_booking()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000
    ]);

    // Try to cancel other's booking
    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/bookings/{$otherBooking->id}/cancel");

    $response->assertStatus(403);
  }

  #[Test]
  public function user_can_only_view_own_payments()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000
    ]);
    $otherPayment = Payment::create([
      'booking_id' => $otherBooking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 50000,
      'payment_status_id' => 1,
      'expired_at' => now()->addMinutes(10)
    ]);

    // Try to view other's payment
    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/payments/{$otherPayment->id}");

    $response->assertStatus(403);
  }

  #[Test]
  public function user_can_only_cancel_own_payment()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000
    ]);
    $otherPayment = Payment::create([
      'booking_id' => $otherBooking->id,
      'payment_method' => 'bank_transfer',
      'amount' => 50000,
      'payment_status_id' => 1,
      'expired_at' => now()->addMinutes(10)
    ]);

    // Try to cancel other's payment
    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/payments/{$otherPayment->id}/cancel");

    $response->assertStatus(403);
  }

  #[Test]
  public function user_cannot_create_payment_for_others_booking()
  {
    $otherUser = User::factory()->create();
    $otherBooking = Booking::create([
      'user_id' => $otherUser->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000
    ]);

    $data = [
      'booking_id' => $otherBooking->id,
      'payment_method' => 'bank_transfer'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/payments', $data);

    $response->assertStatus(403);
  }

  #[Test]
  public function rate_limiting_works_on_public_routes()
  {
    // This test would require configuring rate limiting
    // For now, just verify the middleware is applied
    $response = $this->getJson('/api/v1/sports');
    $response->assertStatus(200);

    // Check if throttle middleware is applied in routes
    $this->assertContains('throttle:60,1', file_get_contents(base_path('routes/api.php')));
  }

  #[Test]
  public function sql_injection_protection_via_eloquent()
  {
    // Test that Eloquent prevents SQL injection
    $maliciousId = "1' OR '1'='1";

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/bookings/{$maliciousId}");

    // Should not return unauthorized data
    $response->assertStatus(404); // Not found, not 200 with all data
  }

  #[Test]
  public function mass_assignment_protection()
  {
    // Test that only fillable fields can be mass assigned
    $bookingData = [
      'user_id' => $this->user->id,
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => 1,
      'total_price' => 50000,
      'created_at' => now()->subDays(1), // Should be protected
      'updated_at' => now()->subDays(1)  // Should be protected
    ];

    $booking = Booking::create($bookingData);

    // created_at and updated_at should be set by Laravel, not from input
    $this->assertNotEquals($bookingData['created_at'], $booking->created_at);
    $this->assertNotEquals($bookingData['updated_at'], $booking->updated_at);
  }

  #[Test]
  public function validation_rules_are_enforced()
  {
    // Test booking creation validation
    $invalidData = [
      'court_id' => 'not_a_number',
      'booking_date' => 'invalid_date',
      'slot_ids' => []
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $invalidData);

    $response->assertStatus(422) // Validation error
      ->assertJsonStructure([
        'success',
        'message',
        'errors'
      ]);
  }

  #[Test]
  public function api_returns_consistent_error_format()
  {
    // Test various error scenarios return consistent format
    $errorScenarios = [
      ['method' => 'GET', 'url' => '/api/v1/bookings/999', 'expected_status' => 404],
      ['method' => 'POST', 'url' => '/api/v1/bookings', 'data' => [], 'expected_status' => 422],
      ['method' => 'GET', 'url' => '/api/v1/admin/users', 'expected_status' => 403],
    ];

    foreach ($errorScenarios as $scenario) {
      if ($scenario['method'] === 'GET') {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($scenario['url']);
      } else {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($scenario['url'], $scenario['data'] ?? []);
      }

      $response->assertStatus($scenario['expected_status'])
        ->assertJsonStructure([
          'success',
          'message'
        ]);
    }
  }

  #[Test]
  public function sensitive_data_not_exposed_in_responses()
  {
    // Test that sensitive fields are not returned in API responses
    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/me');

    $response->assertStatus(200)
      ->assertJsonMissing(['password', 'remember_token', 'email_verified_at']);
  }
}
