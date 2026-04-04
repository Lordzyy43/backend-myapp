<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run essential seeders for tests
        $this->seed([
            \Database\Seeders\BookingStatusSeeder::class,
            \Database\Seeders\PaymentStatusSeeder::class,
        ]);

        // Create roles if they don't exist
        \App\Models\Role::firstOrCreate(['role_name' => 'user']);
        \App\Models\Role::firstOrCreate(['role_name' => 'admin']);
        \App\Models\Role::firstOrCreate(['role_name' => 'owner']);

        // Create test data for courts and time slots
        $this->createTestData();
    }

    private function createTestData()
    {
        // Create owner user first
        $ownerRole = \App\Models\Role::where('role_name', 'owner')->first();
        $owner = \App\Models\User::factory()->create([
            'role_id' => $ownerRole->id,
            'name' => 'Test Owner'
        ]);

        // Create venue
        $venue = \App\Models\Venue::factory()->create([
            'name' => 'Test Venue',
            'slug' => 'test-venue',
            'address' => 'Test Address',
            'city' => 'Test City',
            'description' => 'Test venue description'
        ]);

        // Create sport
        $sport = \App\Models\Sport::firstOrCreate([
            'name' => 'Badminton'
        ]);

        // Create courts
        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Court::firstOrCreate([
                'id' => $i
            ], [
                'venue_id' => $venue->id,
                'name' => "Court {$i}",
                'sport_id' => $sport->id,
                'price_per_hour' => 50000
            ]);
        }

        // Create time slots
        $slots = [
            ['id' => 1, 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
            ['id' => 2, 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
            ['id' => 3, 'start_time' => '10:00:00', 'end_time' => '11:00:00'],
            ['id' => 4, 'start_time' => '11:00:00', 'end_time' => '12:00:00'],
            ['id' => 5, 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
        ];

        foreach ($slots as $slot) {
            \App\Models\TimeSlot::firstOrCreate([
                'id' => $slot['id']
            ], $slot);
        }
    }
}
