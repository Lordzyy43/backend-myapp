<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Panggil Seeder pendukung
        $this->call([
            BookingStatusSeeder::class,
            PaymentStatusSeeder::class,
        ]);

        // 2. Buat Role (Pastikan pakai role_name)
        $customerRole = \App\Models\Role::firstOrCreate(['role_name' => 'user']);
        $adminRole    = \App\Models\Role::firstOrCreate(['role_name' => 'admin']);
        $ownerRole    = \App\Models\Role::firstOrCreate(['role_name' => 'owner']);

        // 3. Buat User Admin Pertama
        $adminUser = \App\Models\User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => 'password123', // Factory akan otomatis hash karena di User model sudah di-cast
            'phone' => '081234567890',
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        // 4. Create test owner user
        $ownerUser = \App\Models\User::factory()->create([
            'name' => 'Venue Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'phone' => '082345678901',
            'role_id' => $ownerRole->id,
            'email_verified_at' => now(),
        ]);

        // 5. Create test customer users
        $customer1 = \App\Models\User::factory()->create([
            'name' => 'Customer One',
            'email' => 'customer1@example.com',
            'password' => 'password123',
            'phone' => '083456789012',
            'role_id' => $customerRole->id,
            'email_verified_at' => now(),
        ]);

        $customer2 = \App\Models\User::factory()->create([
            'name' => 'Customer Two',
            'email' => 'customer2@example.com',
            'password' => 'password123',
            'phone' => '084567890123',
            'role_id' => $customerRole->id,
            'email_verified_at' => now(),
        ]);

        // 6. Create test sports
        $sports = \App\Models\Sport::factory(3)->create();

        // 7. Create test venue for owner
        $venue = \App\Models\Venue::create([
            'owner_id' => $ownerUser->id,
            'name' => 'Test Badminton Court',
            'address' => '123 Main Street',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12345',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'phone_number' => '021-1234567',
            'email' => 'venue@example.com',
            'is_active' => true,
        ]);

        // 8. Create operating hours for venue
        $operatingHours = [
            ['day_of_week' => 1, 'opening_time' => '08:00', 'closing_time' => '22:00', 'is_closed' => false], // Monday
            ['day_of_week' => 2, 'opening_time' => '08:00', 'closing_time' => '22:00', 'is_closed' => false], // Tuesday
            ['day_of_week' => 3, 'opening_time' => '08:00', 'closing_time' => '22:00', 'is_closed' => false], // Wednesday
            ['day_of_week' => 4, 'opening_time' => '08:00', 'closing_time' => '22:00', 'is_closed' => false], // Thursday
            ['day_of_week' => 5, 'opening_time' => '08:00', 'closing_time' => '23:00', 'is_closed' => false], // Friday
            ['day_of_week' => 6, 'opening_time' => '09:00', 'closing_time' => '23:00', 'is_closed' => false], // Saturday
            ['day_of_week' => 0, 'opening_time' => '09:00', 'closing_time' => '22:00', 'is_closed' => false], // Sunday
        ];

        foreach ($operatingHours as $hour) {
            \App\Models\VenueOperatingHour::create(array_merge(['venue_id' => $venue->id], $hour));
        }

        // 9. Create test courts for venue
        for ($i = 1; $i <= 3; $i++) {
            $court = \App\Models\Court::create([
                'venue_id' => $venue->id,
                'sport_id' => $sports->random()->id,
                'name' => "Court {$i}",
                'price_per_hour' => 50000 + ($i * 10000),
                'max_capacity' => 4,
                'status' => 'active',
                'is_active' => true,
            ]);

            // Create time slots for this court
            $this->createTimeSlots($court);
        }

        // 10. Create test promo
        \App\Models\Promo::create([
            'promo_code' => 'WELCOME20',
            'description' => 'Welcome offer for new users',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_discount' => 50000,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'usage_limit' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        \App\Models\Promo::create([
            'promo_code' => 'HOLIDAY50',
            'description' => 'Holiday special - fixed discount',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'max_discount' => null,
            'start_date' => now(),
            'end_date' => now()->addDays(15),
            'usage_limit' => 50,
            'used_count' => 0,
            'is_active' => true,
        ]);

        // Output pesan di terminal biar kelihatan kalau sukses
        $this->command->info('✅ Roles created successfully (admin, user, owner)!');
        $this->command->info('✅ Test users created!');
        $this->command->info('✅ Test venue with courts created!');
        $this->command->info('✅ Test promo codes created!');
        $this->command->line('');
        $this->command->info('Test Accounts:');
        $this->command->info('  Admin: admin@example.com / password123');
        $this->command->info('  Owner: owner@example.com / password123');
        $this->command->info('  Customer 1: customer1@example.com / password123');
        $this->command->info('  Customer 2: customer2@example.com / password123');
    }

    /**
     * Create time slots for a court (1-hour slots from 8 AM to 10 PM)
     */
    private function createTimeSlots(\App\Models\Court $court): void
    {
        for ($day = 0; $day < 7; $day++) {
            $startHour = 8;
            $endHour = 22;

            for ($hour = $startHour; $hour < $endHour; $hour++) {
                \App\Models\TimeSlot::create([
                    'court_id' => $court->id,
                    'day_of_week' => $day,
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'is_active' => true,
                ]);
            }
        }
    }
}
