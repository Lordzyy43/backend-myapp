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

            // 🔥 tanggal booking (slot ada di pivot)
            $table->date('booking_date');

            // 🔥 status booking
            $table->foreignId('status_id')
                ->constrained('booking_status');

            // 🔥 harga total dari slot
            $table->decimal('total_price', 10, 2)->unsigned();

            // 🔥 AUTO CANCEL SYSTEM
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXING (OPTIMIZED FOR SLOT SYSTEM)
            |--------------------------------------------------------------------------
            */

            // 🔥 untuk cek availability cepat
            $table->index(['court_id', 'booking_date']);

            // 🔥 untuk history user
            $table->index(['user_id', 'booking_date']);

            // 🔥 untuk filtering status (dashboard / admin)
            $table->index(['status_id']);
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
