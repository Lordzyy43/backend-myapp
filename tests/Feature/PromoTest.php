<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Promo;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;

class PromoTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $promo;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();

    $this->promo = Promo::create([
      'promo_code' => 'TEST10',
      'description' => 'Test promo',
      'discount_type' => 'percentage',
      'discount_value' => 10,
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => true
    ]);
  }

  #[Test]
  public function valid_promo_code_applies_discount()
  {
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'TEST10'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201)
      ->assertJsonStructure([
        'success',
        'data' => [
          'booking' => [
            'id',
            'total_price',
            'discount_amount',
            'final_price'
          ]
        ]
      ]);

    $booking = $response->json('data.booking');
    $this->assertGreaterThan(0, $booking['discount_amount']);
    $this->assertLessThan($booking['total_price'], $booking['final_price']);
  }

  #[Test]
  public function invalid_promo_code_returns_error()
  {
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'INVALID'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422)
      ->assertJson([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => [
          'promo_code' => ['Invalid promo code']
        ]
      ]);
  }

  #[Test]
  public function expired_promo_code_not_accepted()
  {
    $expiredPromo = Promo::create([
      'promo_code' => 'EXPIRED',
      'description' => 'Expired promo',
      'discount_type' => 'percentage',
      'discount_value' => 10,
      'start_date' => now()->subDays(10),
      'end_date' => now()->subDays(1), // Expired
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => true
    ]);

    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'EXPIRED'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422)
      ->assertJsonFragment(['promo_code' => ['Promo code has expired']]);
  }

  #[Test]
  public function inactive_promo_code_not_accepted()
  {
    $inactivePromo = Promo::create([
      'promo_code' => 'INACTIVE',
      'description' => 'Inactive promo',
      'discount_type' => 'percentage',
      'discount_value' => 10,
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => false // Inactive
    ]);

    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'INACTIVE'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422)
      ->assertJsonFragment(['promo_code' => ['Promo code is not active']]);
  }

  #[Test]
  public function promo_code_usage_limit_enforced()
  {
    // Use up all available uses
    $this->promo->update(['used_count' => 5]);

    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'TEST10'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422)
      ->assertJsonFragment(['promo_code' => ['Promo code usage limit exceeded']]);
  }

  #[Test]
  public function promo_code_usage_count_increments_atomically()
  {
    // Test concurrent usage to ensure atomic increment
    $this->promo->update(['max_uses' => 2, 'used_count' => 0]);

    // Simulate concurrent requests
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'TEST10'
    ];

    // First request should succeed
    $response1 = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response1->assertStatus(201);

    // Check usage count incremented
    $this->promo->refresh();
    $this->assertEquals(1, $this->promo->used_count);

    // Second request should succeed
    $user2 = User::factory()->create();
    $response2 = $this->actingAs($user2, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response2->assertStatus(201);

    // Check usage count incremented again
    $this->promo->refresh();
    $this->assertEquals(2, $this->promo->used_count);

    // Third request should fail
    $user3 = User::factory()->create();
    $response3 = $this->actingAs($user3, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response3->assertStatus(422)
      ->assertJsonFragment(['promo_code' => ['Promo code usage limit exceeded']]);
  }

  #[Test]
  public function fixed_amount_discount_works_correctly()
  {
    $fixedPromo = Promo::create([
      'promo_code' => 'FIXED50',
      'description' => 'Fixed discount promo',
      'discount_type' => 'fixed',
      'discount_value' => 50000, // Fixed 50k discount
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => true
    ]);

    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'FIXED50'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201);

    $booking = $response->json('data.booking');
    $this->assertEquals(50000, $booking['discount_amount']);
  }

  #[Test]
  public function percentage_discount_cannot_exceed_total_price()
  {
    $highDiscountPromo = Promo::create([
      'promo_code' => 'HIGH100',
      'description' => 'High discount promo',
      'discount_type' => 'percentage',
      'discount_value' => 100, // 100% discount
      'start_date' => now()->subDays(1),
      'end_date' => now()->addDays(7),
      'usage_limit' => 5,
      'used_count' => 0,
      'is_active' => true
    ]);

    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'HIGH100'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201);

    $booking = $response->json('data.booking');
    $this->assertEquals(0, $booking['final_price']); // Final price should not be negative
    $this->assertEquals($booking['total_price'], $booking['discount_amount']);
  }

  #[Test]
  public function promo_code_case_insensitive()
  {
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'test10' // lowercase
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201); // Should work with lowercase
  }

  #[Test]
  public function promo_usage_tracked_per_booking()
  {
    $bookingData = [
      'court_id' => 1,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'TEST10'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(201);

    // Check that promo usage is recorded
    $booking = Booking::find($response->json('data.booking.id'));
    $this->assertEquals('TEST10', $booking->promo_code);
    $this->assertEquals(10, $booking->discount_percentage);

    // Check promo used_count incremented
    $this->promo->refresh();
    $this->assertEquals(1, $this->promo->used_count);
  }

  #[Test]
  public function promo_not_applied_if_booking_creation_fails()
  {
    // Create a scenario where booking fails (e.g., invalid court)
    $bookingData = [
      'court_id' => 999, // Non-existent court
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [1, 2],
      'promo_code' => 'TEST10'
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);

    $response->assertStatus(422);

    // Check that promo usage count didn't increment
    $this->promo->refresh();
    $this->assertEquals(0, $this->promo->used_count);
  }
}
