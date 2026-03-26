<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class TimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_time',
        'end_time',
        'order_index',
        'is_active',
        'label',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_time_slots')
            ->withPivot(['court_id', 'booking_date'])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    // 🔥 Hitung durasi (dynamic, bukan dari DB)
    public function getDurationMinutes()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $start->diffInMinutes($end);
    }

    // 🔥 Format label otomatis
    public function getFormattedLabel()
    {
        return $this->start_time . ' - ' . $this->end_time;
    }
}
