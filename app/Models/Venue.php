<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Venue extends Model
{
    use HasFactory;

    protected $table = 'venues';

    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'city',
        'description',
        'slug',
    ];

    protected $casts = [
        'description' => 'string',
    ];

    /**
     * AUTO SLUG
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($venue) {
            if (empty($venue->slug)) {
                $venue->slug = Str::slug($venue->name);
            }
        });
    }

    /**
     * RELATIONSHIPS
     */

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function courts()
    {
        return $this->hasMany(Court::class);
    }

    public function images()
    {
        return $this->hasMany(VenueImage::class);
    }

    public function operatingHours()
    {
        return $this->hasMany(VenueOperatingHour::class);
    }

    public function promos()
    {
        return $this->hasMany(Promo::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
