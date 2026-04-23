<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Info Role
            'role' => [
                'id' => $this->role_id,
                'name' => $this->role->role_name ?? 'Unknown',
                'is_admin' => $this->isAdmin(),
                'is_owner' => $this->isOwner(),
            ],

            // Statistik Aktivitas (Ringkasan untuk Dashboard Admin)
            'stats' => [
                'total_bookings' => $this->when($this->isUser(), fn() => $this->bookings()->count()),
                'total_venues' => $this->when($this->isOwner(), fn() => $this->venues()->count()),
                'total_reviews' => $this->reviews()->count(),
            ],

            // Status Verifikasi
            'is_verified' => $this->email_verified_at !== null,
            'verified_at' => $this->email_verified_at?->toDateTimeString(),

            // Timestamps
            'joined_at' => $this->created_at->format('d M Y'),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
