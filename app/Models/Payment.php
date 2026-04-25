<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payment_method',
        'amount',
        'snap_token',
        'snap_url',
        'transaction_id',
        'payment_status_id',
        'paid_at',
        'expired_at',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'payload' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */
    const PENDING   = 1;
    const PAID      = 2;
    const EXPIRED   = 3;
    const CANCELLED = 4;
    const FAILED    = 5;

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke Booking
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Relasi ke Payment Status (Master Data)
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic (Production Standard)
    |--------------------------------------------------------------------------
    */

    /**
     * Cek apakah sudah lunas
     */
    public function isPaid(): bool
    {
        return (int) $this->payment_status_id === self::PAID;
    }

    /**
     * 🔥 ATOMIC MARK AS PAID
     * Digunakan oleh Webhook Midtrans atau Manual Confirm Admin.
     */
    public function markAsPaid(string $transactionId = null, array $midtransPayload = []): bool
    {
        return DB::transaction(function () use ($transactionId, $midtransPayload) {
            // Lock row untuk mencegah double process dari webhook
            $payment = self::where('id', $this->id)->lockForUpdate()->first();

            if ($payment->isPaid()) {
                return true;
            }

            // 1. Update Detail Payment
            $payment->update([
                'payment_status_id' => self::PAID,
                'transaction_id'    => $transactionId ?? $payment->transaction_id,
                'paid_at'           => now(),
                'payload'           => $midtransPayload,
            ]);

            // 2. Update Status Booking (Confirm)
            $payment->booking->update([
                'status_id' => BookingStatus::confirmed()
            ]);

            // 3. Bersihkan Cache Jadwal
            $this->clearAvailabilityCache();

            // 4. Fire Event (Untuk Notifikasi/Email)
            event(new \App\Events\PaymentSuccess($payment));

            return true;
        });
    }

    /**
     * 🔥 MARK AS EXPIRED / FAILED
     * Mengembalikan slot lapangan ke publik.
     */
    public function markAsFailed(string $status = 'failed'): void
    {
        DB::transaction(function () use ($status) {
            $newStatus = ($status === 'expired') ? self::EXPIRED : self::FAILED;

            $this->update(['payment_status_id' => $newStatus]);

            // Booking ikutan expired
            $this->booking->update(['status_id' => BookingStatus::expired()]);

            // Lepas slot agar bisa dibooking orang lain lagi
            $this->booking->timeSlots()->detach();

            // Bersihkan cache supaya slot langsung muncul lagi di pencarian
            $this->clearAvailabilityCache();
        });
    }

    /**
     * Helper untuk invalidasi cache availability
     */
    private function clearAvailabilityCache(): void
    {
        if ($this->booking) {
            $dateStr = Carbon::parse($this->booking->booking_date)->toDateString();
            $cacheKey = "availability_{$this->booking->court_id}_{$dateStr}";
            Cache::forget($cacheKey);
        }
    }

    public function isExpired(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->expired_at ? now()->gt($this->expired_at) : false;
    }
}
