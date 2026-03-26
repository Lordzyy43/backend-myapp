<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourtImage extends Model
{
    use HasFactory;

    protected $table = 'court_images';

    protected $fillable = [
        'court_id',
        'image_url',
    ];

    /**
     * RELATION
     */
    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * 🔥 ACCESSOR (AUTO FULL URL)
     */
    public function getImageUrlAttribute($value)
    {
        // kalau sudah full URL (cloud/CDN)
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // local storage
        return asset('storage/' . $value);
    }

    /**
     * 🔥 RAW PATH (UNTUK DELETE FILE)
     */
    public function getRawPath()
    {
        return $this->attributes['image_url'];
    }
}
