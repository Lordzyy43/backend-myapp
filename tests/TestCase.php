<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Models\Sport;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\VenueOperatingHour;
use Database\Seeders\BookingStatusSeeder;
use Database\Seeders\PaymentStatusSeeder;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Jalankan Seeder Status (Wajib untuk flow Booking & Payment)
        $this->seed([
            BookingStatusSeeder::class,
            PaymentStatusSeeder::class,
        ]);

        // 2. Inisialisasi Roles
        $this->setupRoles();

        // 3. Setup Data Master (Venue, Hours, Courts, Slots)
        $this->createFullTestData();

        // 4. Pastikan Queue berjalan sinkron khusus Testing
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('config:clear');
    }

    /**
     * Setup Roles Dasar
     */
    private function setupRoles(): void
    {
        Role::updateOrCreate(
            ['id' => 1],
            ['role_name' => 'admin']
        );

        Role::updateOrCreate(
            ['id' => 2],
            ['role_name' => 'user']
        );

        Role::updateOrCreate(
            ['id' => 3],
            ['role_name' => 'owner']
        );
    }

    /**
     * Create Full Test Data Environment
     */
    private function createFullTestData(): void
    {
        // A. Create Owner
        $ownerRole = Role::where('role_name', 'owner')->first();
        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'name' => 'Sensei Test Owner'
        ]);

        // B. Create Venue
        $venue = Venue::factory()->create([
            'name' => 'Test Olympic Center',
            'slug' => 'test-olympic-center',
            'address' => 'Jl. Testing No. 123',
            'city' => 'Jakarta',
            'description' => 'Venue khusus untuk Automated Testing'
        ]);

        // C. Create Operating Hours (SYNCED WITH MIGRATION)
        // Kita pakai loop 0-6 karena day_of_week di migrasi kamu adalah tinyInteger
        for ($i = 0; $i <= 6; $i++) {
            VenueOperatingHour::create([
                'venue_id' => $venue->id,
                'day_of_week' => $i, // 0 = Minggu, 1 = Senin, dst.
                'open_time' => '08:00:00',
                'close_time' => '22:00:00',
                // Note: 'is_open' dihapus karena tidak ada di migrasi kamu
            ]);
        }

        // D. Create Sport
        $sport = Sport::firstOrCreate(['name' => 'Badminton']);

        // E. Create Courts (1-5)
        for ($i = 1; $i <= 5; $i++) {
            Court::firstOrCreate(
                ['id' => $i],
                [
                    'venue_id' => $venue->id,
                    'name' => "Court {$i}",
                    'sport_id' => $sport->id,
                    'price_per_hour' => 50000,
                    // Pastikan kolom 'status' ada di migration courts kamu
                ]
            );
        }

        // F. Create Time Slots (08:00 - 13:00)
        $slots = [
            ['id' => 1, 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
            ['id' => 2, 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
            ['id' => 3, 'start_time' => '10:00:00', 'end_time' => '11:00:00'],
            ['id' => 4, 'start_time' => '11:00:00', 'end_time' => '12:00:00'],
            ['id' => 5, 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
        ];

        foreach ($slots as $slot) {
            TimeSlot::firstOrCreate(['id' => $slot['id']], $slot);
        }
    }
}
