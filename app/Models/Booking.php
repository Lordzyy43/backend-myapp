<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'user_id',
        'court_id',
        'booking_date',
        'status_id',
        'total_price',
        'expires_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'expires_at' => 'datetime',
    ];

    /*
    | RELATIONS
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function status()
    {
        return $this->belongsTo(BookingStatus::class, 'status_id');
    }

    public function timeSlots()
    {
        return $this->belongsToMany(TimeSlot::class, 'booking_time_slots')
            ->withTimestamps();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /*
    | BOOT
    */

    protected static function booted()
    {
        static::creating(function ($booking) {
            $booking->booking_code = self::generateBookingCode();
        });
    }

    public static function generateBookingCode()
    {
        return 'BOOK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }

    /*
    | BUSINESS LOGIC
    */

    // 🔥 VALIDASI SLOT (ANTI TABRAKAN)
    public static function isSlotAvailable($courtId, $date, $slotIds)
    {
        return !BookingTimeSlot::where('court_id', $courtId)
            ->where('booking_date', $date)
            ->whereIn('time_slot_id', $slotIds)
            ->exists();
    }

    // 🔥 HITUNG TOTAL
    public function calculateTotalPrice($slotIds)
    {
        $slots = TimeSlot::whereIn('id', $slotIds)->count();

        return $slots * $this->court->price_per_hour;
    }

    // 🔥 SET EXPIRY
    public function setExpiry(int $minutes = 10): void
    {
        $this->expires_at = now()->addMinutes($minutes);
    }

    // 🔥 CHECK EXPIRED
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->expires_at);
    }

    // 🔥 CHECK MASIH AKTIF (INI YANG SERING DIPAKAI)
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    // 🔥 CORE BOOKING PROCESS (ANTI RUSAK)
    public function bookSlots($slotIds)
    {
        return DB::transaction(function () use ($slotIds) {

            // 1. cek ketersediaan
            if (!self::isSlotAvailable($this->court_id, $this->booking_date, $slotIds)) {
                throw new \Exception('Slot already booked');
            }

            // 2. hitung harga
            $this->total_price = $this->calculateTotalPrice($slotIds);
            $this->setExpiry();
            $this->save();

            // 3. attach slot
            $this->timeSlots()->attach($slotIds);

            return $this;
        });
    }
}
