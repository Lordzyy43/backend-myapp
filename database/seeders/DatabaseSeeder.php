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
        \App\Models\User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => 'password123', // Factory akan otomatis hash karena di User model sudah di-cast
            'phone' => '081234567890',
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        // Output pesan di terminal biar kelihatan kalau sukses
        $this->command->info('Roles and Test User created successfully!');
    }
}
