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
        Schema::create('booking_time_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts')
                ->cascadeOnDelete();

            $table->date('booking_date');

            $table->foreignId('time_slot_id')
                ->constrained('time_slots')
                ->cascadeOnDelete();

            // 🔥 ANTI DOUBLE BOOKING (KUNCI UTAMA)
            $table->unique(['court_id', 'booking_date', 'time_slot_id'], 'court_date_slot_unique');

            // 🔥 INDEX UNTUK SEARCH CEPAT (Pilih salah satu saja)
            $table->index(['court_id', 'booking_date'], 'booking_time_slots_booking_time_idx');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_time_slots');
    }
};
