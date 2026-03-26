<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Court extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'courts';

    protected $fillable = [
        'venue_id',
        'sport_id',
        'name',
        'price_per_hour',
        'status',
        'slug',
    ];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
    ];

    /**
     * AUTO SLUG
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($court) {
            if (empty($court->slug)) {
                $court->slug = Str::slug($court->name);
            }
        });
    }

    /**
     * RELATIONSHIPS
     */

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function images()
    {
        return $this->hasMany(CourtImage::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function maintenances()
    {
        return $this->hasMany(CourtMaintenance::class);
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function ratings()
    {
        return $this->reviews()->avg('rating');
    }

    /**
     * HELPER
     */

    public function isAvailable()
    {
        return $this->status === 'active';
    }
}
