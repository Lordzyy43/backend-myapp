<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Sport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    | AUTO GENERATE SLUG
    */
    protected static function booted()
    {
        static::creating(function ($sport) {
            if (!$sport->slug) {
                $sport->slug = Str::slug($sport->name);
            }
        });
    }

    /*
    | RELATIONS (optional future)
    */

    public function courts()
    {
        return $this->hasMany(Court::class);
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'venue_sport');
    }
    /*
    | SCOPES
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
