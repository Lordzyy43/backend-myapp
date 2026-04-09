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
            $table->string('booking_code')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->date('booking_date');
            $table->foreignId('status_id')->constrained('booking_status');
            $table->decimal('total_price', 15, 2)->unsigned();

            // 🔥 PINDAHAN DARI FILE ORANYE (TAMBAHKAN INI)
            $table->string('promo_code')->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->integer('discount_percentage')->default(0)->nullable(); // WAJIB buat Test!
            $table->decimal('final_price', 15, 2)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // 🔥 INDEX PINDAHAN (TAMBAHKAN INI)
            $table->index(['status_id', 'expires_at'], 'bookings_status_expires_idx');
            $table->index(['user_id', 'status_id'], 'bookings_user_status_idx');
            $table->index(['court_id', 'booking_date']);
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
