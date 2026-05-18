<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Admin\ImageResource;
use App\Http\Resources\V1\Admin\SportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'owner' => [
                'id'   => $this->owner_id,
                'name' => $this->owner->name ?? 'N/A',
            ],
            'name'        => $this->name,
            'slug'        => $this->slug,
            'address'     => $this->address,
            'city'        => $this->city,
            'description' => $this->description,

            // Dashboard Stats (Efisien menggunakan withCount dari Controller)
            'stats' => [
                'total_courts'   => (int) ($this->courts_count ?? $this->courts()->count()),
                'total_reviews'  => (int) ($this->reviews_count ?? $this->reviews()->count()),
            ],

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),

            // Relasi (Pastikan panggil Resource folder Admin)
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'sports' => SportResource::collection($this->whenLoaded('sports')),
        ];
    }
}
