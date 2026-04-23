<?php

namespace App\Http\Resources\V1\Public;

use App\Http\Resources\V1\Shared\ImageResource;
use App\Http\Resources\V1\Shared\SportResource;
use App\Http\Resources\V1\Shared\OperatingHourResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => [
                'address' => $this->address,
                'city' => $this->city,
            ],

            // Statistik (Computed)
            'rating_avg' => round($this->reviews()->avg('rating') ?? 0, 1),
            'reviews_count' => $this->reviews()->count(),

            // Relasi menggunakan Shared Resources yang sudah kita buat
            'primary_image' => new ImageResource($this->images()->where('is_primary', true)->first()),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'sports' => SportResource::collection($this->whenLoaded('sports')),
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),

            // Lapangan yang tersedia di venue ini
            'courts_count' => $this->courts()->count(),
            'courts' => CourtResource::collection($this->whenLoaded('courts')),
        ];
    }
}
