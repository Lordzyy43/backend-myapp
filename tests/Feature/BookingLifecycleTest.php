<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class BookingLifecycleTest extends TestCase
{
  use RefreshDatabase, WithFaker;

  protected $user;
  protected $court;
  protected $timeSlots;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. SEEDER STATUS (firstOrCreate agar tidak bentrok)
    $statuses = ['pending', 'confirmed', 'cancelled', 'expired', 'finished'];
    foreach ($statuses as $status) {
      BookingStatus::firstOrCreate(['status_name' => $status]);
    }

    // 2. CREATE ACTORS (Gunakan role_id, Hapus kolom 'role')
    $adminRole = \App\Models\Role::firstOrCreate(['role_name' => 'admin']);
    // Sesuaikan angka 1, 2, 3 dengan role_id yang kamu punya di database
    $this->admin = User::factory()->create(['role_id' => 1]);
    $this->owner = User::factory()->create(['role_id' => 2]);
    $this->user = User::factory()->create(['role_id' => 3]);

    // 3. CREATE INFRASTRUCTURE
    $sport = Sport::firstOrCreate(['name' => 'Basketball']);

    $this->venue = Venue::create([
      'owner_id' => $this->owner->id,
      'sport_id' => $sport->id,
      'name' => 'Test Venue',
      'address' => 'Test Address',
      'city' => 'Semarang',
      'phone' => '08123456789',
      'email' => 'venue' . rand(1, 99) . '@test.com',
      'slug' => 'test-venue-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    $this->court = Court::create([
      'venue_id' => $this->venue->id,
      'sport_id' => $sport->id,
      'name' => 'Court 1',
      'price_per_hour' => 50000,
      'description' => 'Test court',
      'slug' => 'court-1-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    $this->timeSlots = collect([
      TimeSlot::firstOrCreate(['start_time' => '08:00', 'end_time' => '09:00', 'order_index' => 1, 'is_active' => true]),
      TimeSlot::firstOrCreate(['start_time' => '09:00', 'end_time' => '10:00', 'order_index' => 2, 'is_active' => true]),
    ]);
  }

  #[Test]
  public function user_can_create_booking()
  {
    $data = [
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => $this->timeSlots->pluck('id')->toArray()
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $data);

    $response->assertStatus(201)
      ->assertJsonStructure([
        'success',
        'message',
        'data' => [
          'booking' => [
            'id',
            'booking_code',
            'user_id',
            'court_id',
            'booking_date',
            'status_id',
            'total_price',
            'expires_at'
          ]
        ]
      ]);

    $this->assertDatabaseHas('bookings', [
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'status_id' => BookingStatus::pending(),
      'total_price' => 100000 // 2 slots * 50000
    ]);
  }

  #[Test]
  public function booking_creation_fails_with_double_booking()
  {
    // Create first booking
    $data = [
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [$this->timeSlots->first()->id]
    ];

    $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $data)
      ->assertStatus(201);

    // Try to create second booking with same slot
    $user2 = User::factory()->create();
    $response = $this->actingAs($user2, 'sanctum')
      ->postJson('/api/v1/bookings', $data);

    $response->assertStatus(400)
      ->assertJson([
        'success' => false,
        'message' => 'Slot sudah dibooking'
      ]);
  }

  #[Test]
  public function admin_can_approve_pending_booking()
  {
    // 1. Pastikan Role Admin ada di DB Testing (Pakai firstOrCreate biar gak duplicate error)
    $adminRole = \App\Models\Role::firstOrCreate(['role_name' => 'admin']);

    // 2. Buat booking (pake data yang sudah ada di setUp)
    $booking = Booking::create([
      'user_id'      => $this->user->id,
      'court_id'     => $this->court->id,
      'booking_code' => 'BL001',
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id'    => BookingStatus::pending(),
      'total_price'  => 50000
    ]);

    // 3. Buat Admin dengan cara yang BENAR (langsung inject role_id saat create)
    $admin = User::factory()->create([
      'role_id' => $adminRole->id
    ]);

    // 4. LOAD RELASI (Ini wajib biar method $admin->isAdmin() di middleware bisa baca nama rolenya)
    $admin->load('role');

    // 5. Eksekusi
    $response = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/approve");

    // 6. Assertions
    $response->assertStatus(200)
      ->assertJson([
        'success' => true,
        'message' => 'Booking berhasil di-approve'
      ]);

    $this->assertDatabaseHas('bookings', [
      'id'        => $booking->id,
      'status_id' => BookingStatus::confirmed()
    ]);
  }

  #[Test]
  public function admin_can_reject_pending_booking()
  {
    // Create booking with slots
    $booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'BL002',
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000
    ]);

    $booking->timeSlots()->attach($this->timeSlots->first()->id, [
      'court_id' => $this->court->id,
      'booking_date' => $booking->booking_date
    ]);

    $adminRole = Role::where('role_name', 'admin')->first();
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $admin->load('role');

    $response = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/reject");

    $response->assertStatus(200);

    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'status_id' => BookingStatus::cancelled()
    ]);

    // Check slots are detached
    $this->assertDatabaseMissing('booking_time_slots', [
      'booking_id' => $booking->id
    ]);
  }

  #[Test]
  public function admin_can_finish_confirmed_booking()
  {
    $booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'BL003',
      'booking_date' => now()->subDays(1)->toDateString(), // Past date
      'status_id' => BookingStatus::confirmed(),
      'total_price' => 50000
    ]);

    // Attach time slots to booking with required pivot data
    foreach ($this->timeSlots as $slot) {
      $booking->timeSlots()->attach($slot->id, [
        'court_id' => $this->court->id,
        'booking_date' => $booking->booking_date
      ]);
    }

    $adminRole = Role::where('role_name', 'admin')->first();
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $admin->load('role');

    $response = $this->actingAs($admin, 'sanctum')
      ->patchJson("/api/v1/admin/bookings/{$booking->id}/finish");

    $response->assertStatus(200);

    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'status_id' => BookingStatus::finished()
    ]);
  }

  #[Test]
  public function user_can_cancel_own_booking()
  {
    $booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'BL004',
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000
    ]);

    $booking->timeSlots()->attach($this->timeSlots->first()->id, [
      'court_id' => $this->court->id,
      'booking_date' => $booking->booking_date
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/bookings/{$booking->id}/cancel");

    $response->assertStatus(200);

    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'status_id' => BookingStatus::cancelled()
    ]);

    // Check slots detached
    $this->assertDatabaseMissing('booking_time_slots', [
      'booking_id' => $booking->id
    ]);
  }

  #[Test]
  public function user_cannot_cancel_others_booking()
  {
    $booking = Booking::create([
      'user_id' => User::factory()->create()->id, // Different user
      'court_id' => $this->court->id,
      'booking_code' => 'BL005',
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->patchJson("/api/v1/bookings/{$booking->id}/cancel");

    $response->assertStatus(403); // Forbidden
  }

  #[Test]
  public function booking_expires_automatically()
  {
    $booking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::pending(),
      'total_price' => 50000,
      'expires_at' => now()->subMinutes(1) // Already expired
    ]);

    $booking->timeSlots()->attach($this->timeSlots->first()->id, [
      'court_id' => $this->court->id,
      'booking_date' => $booking->booking_date
    ]);

    // Run expire command
    $this->artisan('booking:expire');

    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'status_id' => BookingStatus::expired()
    ]);

    // Check slots detached
    $this->assertDatabaseMissing('booking_time_slots', [
      'booking_id' => $booking->id
    ]);
  }

  #[Test]
  public function booking_creation_fails_with_invalid_slots()
  {
    $data = [
      'court_id' => $this->court->id,
      'booking_date' => now()->addDays(1)->toDateString(),
      'slot_ids' => [999] // Invalid slot ID
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $data);

    $response->assertUnprocessable();
  }

  #[Test]
  public function booking_creation_fails_with_past_date()
  {
    $data = [
      'court_id' => $this->court->id,
      'booking_date' => now()->subDays(1)->toDateString(),
      'slot_ids' => $this->timeSlots->pluck('id')->toArray()
    ];

    $response = $this->actingAs($this->user, 'sanctum')
      ->postJson('/api/v1/bookings', $data);

    $response->assertUnprocessable();
  }

  #[Test]
  public function user_can_list_own_bookings()
  {
    // Create bookings for user
    Booking::factory()->count(3)->create(['user_id' => $this->user->id]);

    // Create booking for another user
    Booking::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson('/api/v1/bookings');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'message',
        'data' => [
          'bookings' => [
            '*' => [
              'id',
              'booking_code',
              'user_id',
              'court_id',
              'booking_date',
              'status_id',
              'total_price',
              'promo_code',
              'discount',
              'final_price',
              'court'
            ]
          ],
          'meta' => [
            'current_page',
            'last_page',
            'per_page',
            'total'
          ]
        ]
      ]);

    // Should only return 3 bookings for this user
    $this->assertCount(3, $response->json('data.bookings'));
  }

  #[Test]
  public function user_can_view_own_booking_detail()
  {
    $booking = Booking::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(200)
      ->assertJsonStructure([
        'success',
        'data' => [
          'id',
          'booking_code',
          'court',
          'time_slots',
          'status'
        ]
      ]);
  }

  #[Test]
  public function user_cannot_view_others_booking()
  {
    $booking = Booking::factory()->create(); // Different user

    $response = $this->actingAs($this->user, 'sanctum')
      ->getJson("/api/v1/bookings/{$booking->id}");

    $response->assertStatus(403);
  }

  #[Test]
  public function admin_can_list_all_bookings_with_filters()
  {
    Booking::factory()->count(5)->create();
    Booking::factory()->create(['status_id' => BookingStatus::confirmed()]);

    $adminRole = Role::where('role_name', 'admin')->first();
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $admin->load('role');

    $response = $this->actingAs($admin, 'sanctum')
      ->getJson('/api/v1/admin/bookings/reports?status_id=' . BookingStatus::confirmed());

    $response->assertStatus(200);

    // Should only return confirmed bookings
    $data = $response->json('data.data');
    $this->assertCount(1, $data);
    $this->assertEquals(BookingStatus::confirmed(), $data[0]['status_id']);
  }
}
