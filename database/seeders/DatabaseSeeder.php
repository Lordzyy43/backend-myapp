<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
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
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Test',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );

        // 4. Create test owner user
        $ownerUser = \App\Models\User::updateOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Venue Owner',
                'password' => Hash::make('password123'),
                'phone' => '082345678901',
                'role_id' => $ownerRole->id,
                'email_verified_at' => now(),
            ]
        );

        // 5. Create test customer users
        \App\Models\User::updateOrCreate(
            ['email' => 'customer1@example.com'],
            [
                'name' => 'Customer One',
                'password' => Hash::make('password123'),
                'phone' => '083456789012',
                'role_id' => $customerRole->id,
                'email_verified_at' => now(),
            ]
        );

        \App\Models\User::updateOrCreate(
            ['email' => 'customer2@example.com'],
            [
                'name' => 'Customer Two',
                'password' => Hash::make('password123'),
                'phone' => '084567890123',
                'role_id' => $customerRole->id,
                'email_verified_at' => now(),
            ]
        );

        // 6. Create test sports
        $sportNames = ['Badminton', 'Futsal', 'Basketball'];
        $sports = collect($sportNames)->map(fn($name) => \App\Models\Sport::firstOrCreate(['name' => $name]));

        // 7. Create test venue for owner
        $venue = \App\Models\Venue::updateOrCreate(
            ['slug' => 'test-badminton-court'],
            [
                'owner_id' => $ownerUser->id,
                'name' => 'Test Badminton Court',
                'address' => '123 Main Street',
                'city' => 'Jakarta',
                'description' => 'Local seed venue for API smoke testing',
            ]
        );

        // 8. Create operating hours for venue
        $operatingHours = [
            ['day_of_week' => 0, 'open_time' => '09:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 1, 'open_time' => '08:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 2, 'open_time' => '08:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 3, 'open_time' => '08:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 4, 'open_time' => '08:00:00', 'close_time' => '22:00:00'],
            ['day_of_week' => 5, 'open_time' => '08:00:00', 'close_time' => '23:00:00'],
            ['day_of_week' => 6, 'open_time' => '09:00:00', 'close_time' => '23:00:00'],
        ];

        foreach ($operatingHours as $hour) {
            \App\Models\VenueOperatingHour::updateOrCreate(
                ['venue_id' => $venue->id, 'day_of_week' => $hour['day_of_week']],
                ['open_time' => $hour['open_time'], 'close_time' => $hour['close_time']]
            );
        }

        // 9. Create global time slots
        for ($hour = 8; $hour < 22; $hour++) {
            \App\Models\TimeSlot::updateOrCreate(
                ['start_time' => sprintf('%02d:00:00', $hour), 'end_time' => sprintf('%02d:00:00', $hour + 1)],
                [
                    'order_index' => $hour - 7,
                    'is_active' => true,
                    'label' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
                ]
            );
        }

        // 10. Create test courts for venue
        for ($i = 1; $i <= 3; $i++) {
            \App\Models\Court::updateOrCreate(
                ['venue_id' => $venue->id, 'name' => "Court {$i}"],
                [
                    'sport_id' => $sports[($i - 1) % $sports->count()]->id,
                    'price_per_hour' => 50000 + ($i * 10000),
                    'status' => 'active',
                    'slug' => "court-{$i}",
                ]
            );
        }

        // 11. Create test promos
        \App\Models\Promo::updateOrCreate(
            ['promo_code' => 'WELCOME20'],
            [
                'description' => 'Welcome offer for new users',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'usage_limit' => 100,
                'used_count' => 0,
                'is_active' => true,
            ]
        );

        \App\Models\Promo::updateOrCreate(
            ['promo_code' => 'HOLIDAY50'],
            [
                'description' => 'Holiday special - fixed discount',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'start_date' => now(),
                'end_date' => now()->addDays(15),
                'usage_limit' => 50,
                'used_count' => 0,
                'is_active' => true,
            ]
        );

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
}
