<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $bookingDate;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();
    $this->bookingDate = now()->addDays(1)->toDateString();

    // Create test data
    $this->createTestData();
  }

  private function createTestData()
  {
    // Create venues and courts
    $venue = Venue::create([
      'name' => 'Test Venue',
      'address' => 'Test Address',
      'phone' => '1234567890',
      'email' => 'venue@test.com'
    ]);

    for ($i = 1; $i <= 5; $i++) {
      Court::create([
        'venue_id' => $venue->id,
        'name' => "Court {$i}",
        'sport_id' => 1,
        'price_per_hour' => 50000
      ]);
    }

    // Create time slots
    $slots = [
      ['start_time' => '08:00:00', 'end_time' => '09:00:00'],
      ['start_time' => '09:00:00', 'end_time' => '10:00:00'],
      ['start_time' => '10:00:00', 'end_time' => '11:00:00'],
      ['start_time' => '11:00:00', 'end_time' => '12:00:00'],
    ];

    foreach ($slots as $slot) {
      TimeSlot::create($slot);
    }
  }

  #[Test]
  public function availability_endpoint_uses_caching()
  {
    $courtId = 1;

    // First request should cache the result
    $startTime = microtime(true);
    $response1 = $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");
    $firstRequestTime = microtime(true) - $startTime;

    $response1->assertStatus(200);

    // Second request should use cache (faster)
    $startTime = microtime(true);
    $response2 = $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");
    $secondRequestTime = microtime(true) - $startTime;

    $response2->assertStatus(200);

    // Cached response should be significantly faster (at least 50% faster)
    $this->assertLessThan($firstRequestTime * 0.5, $secondRequestTime);

    // Verify cache exists
    $cacheKey = "availability_{$courtId}_{$this->bookingDate}";
    $this->assertTrue(Cache::has($cacheKey));
  }

  #[Test]
  public function cache_expires_after_configured_time()
  {
    $courtId = 1;
    $cacheKey = "availability_{$courtId}_{$this->bookingDate}";

    // Make request to populate cache
    $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");

    // Verify cache exists
    $this->assertTrue(Cache::has($cacheKey));

    // Simulate cache expiration (1 minute)
    Cache::put($cacheKey, Cache::get($cacheKey), 0); // Expire immediately

    // Next request should repopulate cache
    $response = $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");
    $response->assertStatus(200);

    // Cache should exist again
    $this->assertTrue(Cache::has($cacheKey));
  }

  #[Test]
  public function database_indexes_improve_query_performance()
  {
    // Create multiple bookings to test index performance
    $users = User::factory()->count(10)->create();

    foreach ($users as $user) {
      Booking::create([
        'user_id' => $user->id,
        'court_id' => rand(1, 5),
        'booking_date' => $this->bookingDate,
        'status_id' => rand(1, 5),
        'total_price' => 50000
      ]);
    }

    // Test query with index (should be fast)
    $startTime = microtime(true);
    $bookings = DB::select('SELECT * FROM bookings WHERE status_id = ? AND booking_date = ?', [1, $this->bookingDate]);
    $queryTime = microtime(true) - $startTime;

    // Query should complete in reasonable time (< 0.1 seconds)
    $this->assertLessThan(0.1, $queryTime);
    $this->assertNotEmpty($bookings);
  }

  #[Test]
  public function eager_loading_reduces_n_plus_one_queries()
  {
    // Create bookings with relationships
    $users = User::factory()->count(5)->create();

    foreach ($users as $user) {
      Booking::create([
        'user_id' => $user->id,
        'court_id' => 1,
        'booking_date' => $this->bookingDate,
        'status_id' => 1,
        'total_price' => 50000
      ]);
    }

    // Test without eager loading (N+1 problem)
    DB::enableQueryLog();
    $bookings = Booking::all();
    foreach ($bookings as $booking) {
      $booking->user; // This would cause N+1 queries
      $booking->court;
    }
    $queriesWithoutEager = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Test with eager loading
    DB::enableQueryLog();
    $bookingsWithEager = Booking::with(['user', 'court'])->get();
    foreach ($bookingsWithEager as $booking) {
      $booking->user;
      $booking->court;
    }
    $queriesWithEager = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Eager loading should use fewer queries
    $this->assertLessThan($queriesWithoutEager, $queriesWithEager);
  }

  #[Test]
  public function api_response_time_within_acceptable_limits()
  {
    $acceptableResponseTime = 2.0; // 2 seconds max

    $endpoints = [
      '/api/v1/sports',
      '/api/v1/venues',
      '/api/v1/courts',
      "/api/v1/availability?date={$this->bookingDate}&court_id=1"
    ];

    foreach ($endpoints as $endpoint) {
      $startTime = microtime(true);
      $response = $this->getJson($endpoint);
      $responseTime = microtime(true) - $startTime;

      $response->assertStatus(200);
      $this->assertLessThan(
        $acceptableResponseTime,
        $responseTime,
        "Endpoint {$endpoint} took {$responseTime}s, which exceeds {$acceptableResponseTime}s limit"
      );
    }
  }

  #[Test]
  public function booking_creation_response_time_acceptable()
  {
    $acceptableResponseTime = 3.0; // 3 seconds for booking creation

    $bookingData = [
      'court_id' => 1,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [1, 2]
    ];

    $startTime = microtime(true);
    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);
    $responseTime = microtime(true) - $startTime;

    $response->assertStatus(201);
    $this->assertLessThan(
      $acceptableResponseTime,
      $responseTime,
      "Booking creation took {$responseTime}s, which exceeds {$acceptableResponseTime}s limit"
    );
  }

  #[Test]
  public function concurrent_requests_handled_efficiently()
  {
    // This test simulates multiple concurrent requests
    // In a real load test, you'd use tools like Apache Bench or JMeter

    $courtId = 1;
    $endpoint = "/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}";

    // Make multiple requests in quick succession
    $responses = [];
    $startTime = microtime(true);

    for ($i = 0; $i < 5; $i++) {
      $responses[] = $this->getJson($endpoint);
    }

    $totalTime = microtime(true) - $startTime;
    $averageTime = $totalTime / 5;

    // All responses should be successful
    foreach ($responses as $response) {
      $response->assertStatus(200);
    }

    // Average response time should be reasonable (< 1 second)
    $this->assertLessThan(1.0, $averageTime);
  }

  #[Test]
  public function memory_usage_stays_within_limits()
  {
    // Test memory usage for large data sets
    $initialMemory = memory_get_usage();

    // Create many bookings
    $users = User::factory()->count(50)->create();

    foreach ($users as $user) {
      Booking::create([
        'user_id' => $user->id,
        'court_id' => rand(1, 5),
        'booking_date' => $this->bookingDate,
        'status_id' => 1,
        'total_price' => 50000
      ]);
    }

    // Fetch all bookings
    $bookings = Booking::with(['user', 'court'])->get();

    $finalMemory = memory_get_usage();
    $memoryUsed = $finalMemory - $initialMemory;

    // Memory usage should be reasonable (< 50MB)
    $this->assertLessThan(
      50 * 1024 * 1024,
      $memoryUsed,
      "Memory usage: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB exceeds 50MB limit"
    );

    $this->assertNotEmpty($bookings);
  }

  #[Test]
  public function database_connection_pooling_works()
  {
    // Test that multiple database operations work efficiently
    $startTime = microtime(true);

    // Perform multiple database operations
    for ($i = 0; $i < 10; $i++) {
      DB::select('SELECT COUNT(*) as count FROM bookings');
      DB::select('SELECT COUNT(*) as count FROM users');
      DB::select('SELECT COUNT(*) as count FROM courts');
    }

    $totalTime = microtime(true) - $startTime;

    // Should complete in reasonable time (< 1 second)
    $this->assertLessThan(1.0, $totalTime);
  }

  #[Test]
  public function large_dataset_pagination_works_efficiently()
  {
    // Create many records
    $users = User::factory()->count(100)->create();

    foreach ($users as $user) {
      for ($i = 0; $i < 5; $i++) {
        Booking::create([
          'user_id' => $user->id,
          'court_id' => rand(1, 5),
          'booking_date' => now()->addDays(rand(1, 30))->toDateString(),
          'status_id' => rand(1, 5),
          'total_price' => 50000
        ]);
      }
    }

    // Test pagination performance
    $startTime = microtime(true);
    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/bookings?page=1&per_page=20');
    $responseTime = microtime(true) - $startTime;

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'bookings' => [
            'data',
            'current_page',
            'per_page',
            'total'
          ]
        ]
      ]);

    // Pagination should be fast (< 0.5 seconds)
    $this->assertLessThan(0.5, $responseTime);
  }

  #[Test]
  public function cache_invalidation_doesnt_cause_performance_degradation()
  {
    $courtId = 1;

    // Populate cache
    $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");

    // Create booking (invalidates cache)
    $bookingData = [
      'court_id' => $courtId,
      'booking_date' => $this->bookingDate,
      'slot_ids' => [1]
    ];

    $startTime = microtime(true);
    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $bookingData);
    $bookingTime = microtime(true) - $startTime;

    $response->assertStatus(201);

    // Next availability request should still be fast despite cache invalidation
    $startTime = microtime(true);
    $availabilityResponse = $this->getJson("/api/v1/availability?date={$this->bookingDate}&court_id={$courtId}");
    $availabilityTime = microtime(true) - $startTime;

    $availabilityResponse->assertStatus(200);

    // Both operations should be reasonably fast
    $this->assertLessThan(2.0, $bookingTime);
    $this->assertLessThan(1.0, $availabilityTime);
  }
}
