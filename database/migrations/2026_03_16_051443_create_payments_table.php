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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained()
                ->cascadeOnDelete();

            // 🔥 unique: 1 booking = 1 payment (opsional, tergantung bisnis)
            $table->unique('booking_id');

            // 🔥 metode pembayaran
            $table->string('payment_method');
            // transfer_bank, qris, ewallet, dll

            $table->decimal('amount', 12, 2);

            // midtrans,
            $table->string('snap_token')->nullable();
            $table->string('snap_url')->nullable();

            // 🔥 reference dari gateway (midtrans, xendit, dll)
            $table->string('transaction_id')->nullable();

            // 🔥 bukti pembayaran manual
            $table->string('payment_proof')->nullable();

            $table->foreignId('payment_status_id')
                ->constrained('payment_status')
                ->cascadeOnDelete();

            // 🔥 waktu bayar & expiry pembayaran
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            // 🔥 payload response dari gateway (json)
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'payment_status_id'], 'payments_booking_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
