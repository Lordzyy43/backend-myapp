<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VenueImage extends Model
{
    use HasFactory;

    protected $table = 'venue_images';

    protected $fillable = [
        'venue_id',
        'image_url',
    ];

    /**
     * RELATION
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * 🔥 ACCESSOR (FULL URL)
     */
    public function getImageUrlAttribute($value)
    {
        // kalau sudah full URL (cloud), return langsung
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // kalau local storage
        return asset('storage/' . $value);
    }

    /**
     * 🔥 HELPER: get raw path (tanpa asset)
     */
    public function getRawPath()
    {
        return $this->attributes['image_url'];
    }
}
