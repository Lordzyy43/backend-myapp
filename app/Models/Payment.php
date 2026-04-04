<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payment_method',
        'amount',
        'transaction_id',
        'payment_proof',
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
    | RELATION
    */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function status()
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status_id');
    }

    /*
    | BUSINESS LOGIC
    */

    // 🔥 apakah sudah dibayar
    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    // 🔥 apakah expired
    public function isExpired(): bool
    {
        return $this->expired_at && now()->greaterThan($this->expired_at);
    }

    // 🔥 mark as paid
    public function markAsPaid(): void
    {
        $this->update([
            'payment_status_id' => PaymentStatus::paid(),
            'paid_at' => now(),
        ]);

        // 🔥 update booking juga
        $this->booking->update([
            'status_id' => BookingStatus::confirmed(),
            'expires_at' => null
        ]);
    }
}
