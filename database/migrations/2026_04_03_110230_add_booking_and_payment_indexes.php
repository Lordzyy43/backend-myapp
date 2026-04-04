<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status_id', 'expires_at'], 'bookings_status_expires_idx');
            $table->index(['user_id', 'status_id'], 'bookings_user_status_idx');
        });

        Schema::table('booking_time_slots', function (Blueprint $table) {
            $table->index(['court_id', 'booking_date'], 'booking_time_slots_court_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['booking_id', 'payment_status_id'], 'payments_booking_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_status_expires_idx');
            $table->dropIndex('bookings_user_status_idx');
        });

        Schema::table('booking_time_slots', function (Blueprint $table) {
            $table->dropIndex('booking_time_slots_court_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_booking_status_idx');
        });
    }
};
