<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ID 1
        Role::firstOrCreate(['role_name' => 'admin']);
        // ID 2
        Role::firstOrCreate(['role_name' => 'user']);
    }
}
