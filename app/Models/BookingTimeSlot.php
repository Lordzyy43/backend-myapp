<?php

namespace App\Models;

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

    // 🔥 cek apakah slot sudah dipakai
    public static function isBooked($courtId, $date, $slotIds)
    {
        return self::byCourt($courtId)
            ->byDate($date)
            ->bySlots($slotIds)
            ->exists();
    }

    // 🔥 ambil slot yang sudah terpakai
    public static function getBookedSlots($courtId, $date)
    {
        return self::byCourt($courtId)
            ->byDate($date)
            ->pluck('time_slot_id')
            ->toArray();
    }
}
