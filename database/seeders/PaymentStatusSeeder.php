<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentStatus;

class PaymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'pending',
            'paid',
            'cancelled',
            'failed',
            'expired',
        ];

        foreach ($statuses as $status) {
            PaymentStatus::firstOrCreate([
                'status_name' => $status
            ]);
        }
    }
}
