<?php

namespace App\Models;

use App\Models\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingTimeSlot extends Model
{
    use HasFactory;

    protected $table = 'booking_time_slots';

    protected $fillable = [
        'booking_id',
        'court_id',
        'booking_date',
        'time_slot_id',
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public $timestamps = true;

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (QUERY HELPER)
    |--------------------------------------------------------------------------
    */

    // Filter berdasarkan court
    public function scopeByCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    // Filter berdasarkan tanggal
    public function scopeByDate($query, $date)
    {
        return $query->where('booking_date', $date);
    }

    // Filter slot tertentu
    public function scopeBySlots($query, $slotIds)
    {
        return $query->whereIn('time_slot_id', $slotIds);
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC HELPER (ANTI DOUBLE BOOKING CORE)
    |--------------------------------------------------------------------------
    */

    // 🔥 cek apakah slot sudah dipakai (hanya booking aktif: pending/confirmed)
    public static function isBooked($courtId, $date, $slotIds)
    {
        $activeStatusIds = [BookingStatus::pending(), BookingStatus::confirmed()];

        return self::byCourt($courtId)
            ->byDate($date)
            ->bySlots($slotIds)
            ->whereHas('booking', function ($q) use ($activeStatusIds) {
                $q->whereIn('status_id', $activeStatusIds)
                    ->where(function ($q2) {
                        $q2->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->exists();
    }

    // 🔥 ambil slot yang sudah terpakai (hanya booking aktif: pending/confirmed)
    public static function getBookedSlots($courtId, $date)
    {
        // Pastikan string 'Confirmed' dan 'Pending' sesuai dengan yang ada di Factory
        $activeStatusIds = BookingStatus::whereIn('status_name', ['Pending', 'Confirmed'])
            ->pluck('id')
            ->toArray();

        return self::where('court_id', $courtId)
            ->where('booking_date', $date) // Coba hilangkan 'Date' kalau tipenya sudah date
            ->whereHas('booking', function ($q) use ($activeStatusIds) {
                $q->whereIn('status_id', $activeStatusIds)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', now()->toDateTimeString()); // Pakai >= dan string
                    });
            })
            ->pluck('time_slot_id')
            ->toArray();
    }
}
