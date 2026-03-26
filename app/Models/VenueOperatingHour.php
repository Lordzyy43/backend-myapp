<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class VenueOperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * 🔥 CHECK apakah slot ada di jam operasional
     */
    public function isWithinOperatingHours($time)
    {
        return $time >= $this->open_time && $time < $this->close_time;
    }
}
