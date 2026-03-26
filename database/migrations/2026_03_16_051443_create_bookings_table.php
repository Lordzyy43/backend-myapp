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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // 🔥 kode unik untuk user / invoice
            $table->string('booking_code')->unique();

            // 🔥 relasi utama
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('court_id')
                ->constrained('courts')
                ->cascadeOnDelete();

            // 🔥 waktu booking
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');

            // 🔥 status booking
            $table->foreignId('status_id')
                ->constrained('booking_status');

            // 🔥 harga aman
            $table->decimal('total_price', 10, 2)->unsigned();

            // 🔥 AUTO CANCEL SYSTEM (WAJIB)
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 🔥 INDEXING (PERFORMANCE)
            $table->index(['court_id', 'booking_date']);
            $table->index(['court_id', 'booking_date', 'start_time', 'end_time']);
            $table->index(['user_id', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
