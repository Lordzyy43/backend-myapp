<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 🔒 ROLE CONSTANTS (ANTI MAGIC NUMBER)
     */
    public const ROLE_ADMIN = 1;
    public const ROLE_USER = 2;
    public const ROLE_OWNER = 3;

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
     * =========================
     * RELATIONSHIPS
     * =========================
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function venues()
    {
        return $this->hasMany(Venue::class, 'owner_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function promoUsages()
    {
        return $this->hasMany(Promo::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * =========================
     * CORE ROLE CHECK (FAST & SAFE)
     * =========================
     */
    public function isAdmin(): bool
    {
        return $this->role_id === self::ROLE_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role_id === self::ROLE_OWNER;
    }

    public function isUser(): bool
    {
        return $this->role_id === self::ROLE_USER;
    }

    /**
     * =========================
     * FLEXIBLE ROLE CHECK (OPTIONAL)
     * =========================
     * Dipakai hanya kalau butuh nama role
     * (bukan untuk middleware / security)
     */
    public function hasRole(string $roleName): bool
    {
        // fallback aman kalau relation belum load
        if (!$this->relationLoaded('role')) {
            $this->loadMissing('role');
        }

        return $this->role?->role_name === $roleName;
    }
}
