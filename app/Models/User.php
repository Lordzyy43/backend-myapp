<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * RELATIONSHIPS
     */

    // User → Role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // User → Venue (owner)
    public function venues()
    {
        return $this->hasMany(Venue::class, 'owner_id');
    }

    // User → Booking
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // User → PromoUsage
    public function promoUsages()
    {
        return $this->hasMany(Promo::class);
    }

    // User → Review
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // User → Notification
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * HELPER METHODS
     */

    public function isAdmin()
    {
        return $this->role && $this->role->role_name === 'admin';
    }

    public function isOwner()
    {
        return $this->role && $this->role->role_name === 'owner';
    }

    public function isUser()
    {
        return $this->role && $this->role->role_name === 'user';
    }
}
