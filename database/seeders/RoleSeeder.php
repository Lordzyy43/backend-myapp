<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
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
}
