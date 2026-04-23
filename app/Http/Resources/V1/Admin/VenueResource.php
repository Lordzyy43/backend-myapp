<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Shared\ImageResource;
use App\Http\Resources\V1\Shared\SportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->owner->name ?? 'N/A', // Penting untuk Admin
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'city' => $this->city,
            'description' => $this->description,

            // Full data untuk kebutuhan Dashboard Admin
            'stats' => [
                'total_courts' => $this->courts()->count(),
                'total_bookings' => $this->courts()->withCount('bookings')->get()->sum('bookings_count'),
            ],

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),

            // Relasi
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'sports' => SportResource::collection($this->whenLoaded('sports')),
        ];
    }
}
