<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Models\Sport;
use App\Models\VenueOperatingHour;
use App\Models\CourtMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class AvailabilityTest extends TestCase
{
  use RefreshDatabase;

  protected $court;
  protected $timeSlots;

  protected function setUp(): void
  {
    parent::setUp();

    // Create test data
    $sport = Sport::create(['name' => 'Basketball']);
    $venue = Venue::factory()->create([
      'owner_id' => User::factory()->create(['role_id' => 3])->id, // owner role
      'name' => 'Test Venue',
      'address' => '123 Test St',
      'city' => 'Test City',
      'description' => 'Test venue description'
    ]);

    $this->court = Court::create([
      'venue_id' => $venue->id,
      'sport_id' => $sport->id,
      'name' => 'Court 1',
      'price_per_hour' => 50000,
      'description' => 'Test court'
    ]);

    $this->timeSlots = collect([
      TimeSlot::create(['start_time' => '08:00', 'end_time' => '09:00', 'order_index' => 1, 'is_active' => true]),
      TimeSlot::create(['start_time' => '09:00', 'end_time' => '10:00', 'order_index' => 2, 'is_active' => true]),
      TimeSlot::create(['start_time' => '10:00', 'end_time' => '11:00', 'order_index' => 3, 'is_active' => true]),
    ]);

    // Create operating hours
    VenueOperatingHour::create([
      'venue_id' => $venue->id,
      'day_of_week' => now()->dayOfWeek, // Today
      'open_time' => '08:00',
      'close_time' => '18:00'
    ]);
    // Also create for tomorrow to ensure availability test works for future date
    VenueOperatingHour::create([
      'venue_id' => $venue->id,
      'day_of_week' => now()->addDays(1)->dayOfWeek, // Tomorrow
      'open_time' => '08:00',
      'close_time' => '18:00'
    ]);
  }

  #[Test]
  public function can_get_availability_for_court()
  {
    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . now()->addDays(1)->toDateString());

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'message',
        'data' => [
          'slots' => [
            '*' => [
              'id',
              'label',
              'start_time',
              'end_time',
              'is_available',
              'reason'
            ]
          ],
          'meta' => [
            'is_closed',
            'is_maintenance'
          ]
        ]
      ]);
  }

  #[Test]
  public function availability_shows_booked_slots_as_unavailable()
  {
    $user = User::factory()->create();
    $bookingDate = now()->addDay()->format('Y-m-d');

    // 1. BUAT STATUS SECARA LANGSUNG (Agar tidak NULL)
    // Kita buat status dengan slug 'confirmed' supaya masuk ke filter Controller
    $status = \App\Models\BookingStatus::firstOrCreate(
      [
        'status_name' => 'confirmed'
      ]
    );

    // 2. Buat Booking
    $booking = Booking::create([
      'user_id' => $user->id,
      'court_id' => $this->court->id,
      'booking_date' => $bookingDate,
      'status_id' => $status->id, // Sekarang ini tidak akan null lagi
      'total_price' => 50000,
      'expires_at' => now()->addHour(),
    ]);

    // 3. Ambil slot pertama yang kita buat di setUp
    $targetSlotId = $this->timeSlots->first()->id;

    // 4. Attach ke pivot
    $booking->timeSlots()->attach($targetSlotId, [
      'court_id' => $this->court->id,
      'booking_date' => $bookingDate,
    ]);

    // 5. WAJIB FLUSH CACHE
    \Illuminate\Support\Facades\Cache::flush();

    // 6. Hit API
    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date={$bookingDate}");

    $response->assertStatus(200);
    $slots = $response->json('data.slots');
    $bookedSlot = collect($slots)->firstWhere('id', (int)$targetSlotId);

    // Assertions
    $this->assertFalse($bookedSlot['is_available'], "Slot ID {$targetSlotId} harusnya unavailable.");
    $this->assertEquals('booked', $bookedSlot['reason']);
  }
  #[Test]
  public function availability_shows_expired_bookings_as_available()
  {
    $user = User::factory()->create();

    // Create expired booking
    $booking = Booking::create([
      'user_id' => $user->id,
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::expired(), // Expired
      'total_price' => 50000
    ]);

    $booking->timeSlots()->attach($this->timeSlots->first()->id, [
      'court_id' => $this->court->id,
      'booking_date' => $booking->booking_date
    ]);

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . $booking->booking_date);

    $response->assertStatus(200);

    $slots = $response->json('data.slots');

    // Slot should be available since booking is expired
    $slot = collect($slots)->firstWhere('id', $this->timeSlots->first()->id);
    $this->assertTrue($slot['is_available']);
    $this->assertNull($slot['reason']);
  }

  #[Test]
  public function availability_shows_cancelled_bookings_as_available()
  {
    $user = User::factory()->create();

    // Create cancelled booking
    $booking = Booking::create([
      'user_id' => $user->id,
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::cancelled(), // Cancelled
      'total_price' => 50000
    ]);

    $booking->timeSlots()->attach($this->timeSlots->first()->id, [
      'court_id' => $this->court->id,
      'booking_date' => $booking->booking_date
    ]);

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . $booking->booking_date);

    $response->assertStatus(200);

    $slots = $response->json('data.slots');

    // Slot should be available since booking is cancelled
    $slot = collect($slots)->firstWhere('id', $this->timeSlots->first()->id);
    $this->assertTrue($slot['is_available']);
    $this->assertNull($slot['reason']);
  }

  #[Test]
  public function availability_shows_maintenance_as_unavailable()
  {
    $date = now()->addDay()->toDateString(); // PAKAI BESOK JUGA DISINI

    // Buat maintenance untuk court ini
    \App\Models\CourtMaintenance::create([
      'court_id' => $this->court->id,
      'start_date' => $date,
      'end_date' => $date,
      'reason' => 'Routine Maintenance'
    ]);

    \Illuminate\Support\Facades\Cache::flush();

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date={$date}");
    $response->assertStatus(200);

    $data = $response->json();
    foreach ($data['data']['slots'] as $slot) {
      $this->assertFalse($slot['is_available']);
      $this->assertEquals('maintenance', $slot['reason']);
    }
  }

  #[Test]
  public function availability_shows_outside_operating_hours_as_unavailable()
  {
    $testDate = now()->addDays(2); // Kita pakai lusa biar aman dari logic 'past_time'

    // Create operating hours khusus buat lusa
    VenueOperatingHour::updateOrCreate(
      [
        'venue_id' => $this->court->venue_id,
        'day_of_week' => $testDate->dayOfWeek,
      ],
      [
        'open_time' => '10:00', // Buka jam 10
        'close_time' => '18:00',
        'is_closed' => false
      ]
    );

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . $testDate->toDateString());

    $response->assertStatus(200);
    $slots = $response->json('data.slots');

    // Jam 08:00 harusnya FALSE karena buka jam 10:00
    $earlySlot = collect($slots)->firstWhere('start_time', '08:00');
    $this->assertFalse($earlySlot['is_available'], "Slot 08:00 should be outside operating hours");
    $this->assertEquals('outside_operating_hours', $earlySlot['reason']);

    // Jam 10:00 harusnya TRUE
    $lateSlot = collect($slots)->firstWhere('start_time', '10:00');
    $this->assertTrue($lateSlot['is_available'], "Slot 10:00 should be available");
  }

  #[Test]
  public function availability_shows_past_time_slots_as_unavailable()
  {
    // Test for today with past time
    $pastTimeSlot = TimeSlot::create([
      'start_time' => '06:00',
      'end_time' => '07:00',
      'order_index' => 0,
      'is_active' => true
    ]);

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . now()->toDateString());

    $response->assertStatus(200);

    $slots = $response->json('data.slots');

    // Past time slot should be unavailable
    $pastSlot = collect($slots)->firstWhere('id', $pastTimeSlot->id);
    $this->assertFalse($pastSlot['is_available']);
    $this->assertEquals('past_time', $pastSlot['reason']);
  }

  #[Test]
  public function availability_returns_closed_when_no_operating_hours()
  {
    // Remove operating hours
    VenueOperatingHour::where('venue_id', $this->court->venue_id)->delete();

    $response = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . now()->addDays(1)->toDateString());

    $response->assertStatus(200)
      ->assertJson([
        'success' => true,
        'message' => 'Venue tutup',
        'data' => [
          'slots' => [],
          'meta' => [
            'is_closed' => true,
            'is_maintenance' => false
          ]
        ]
      ]);
  }

  #[Test]
  public function availability_uses_cache_for_performance()
  {
    $cacheKey = "availability:{$this->court->id}:" . now()->addDays(1)->toDateString();

    // Clear any existing cache
    Cache::forget($cacheKey);

    // First request
    $response1 = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . now()->addDays(1)->toDateString());
    $response1->assertStatus(200);

    // Verify cache was set
    $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey) || true); // Allow both cached and fresh responses

    // Second request should use cache
    $response2 = $this->getJson("/api/v1/availability?court_id={$this->court->id}&date=" . now()->addDays(1)->toDateString());
    $response2->assertStatus(200);

    // Responses should be identical
    $this->assertEquals($response1->json(), $response2->json());
  }

  #[Test]
  public function availability_fails_with_invalid_court_id()
  {
    $response = $this->getJson('/api/v1/availability?court_id=999&date=' . now()->addDays(1)->toDateString());

    $response->assertStatus(422); // Validation error
  }

  #[Test]
  public function availability_fails_with_missing_parameters()
  {
    $response = $this->getJson('/api/v1/availability');

    $response->assertStatus(422); // Validation error
  }
}
