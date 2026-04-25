<?php

namespace App\Http\Resources\V1\User;

// Pastikan import mengarah ke folder User, bukan Public atau Shared (kecuali file tersebut memang di Shared)
use App\Http\Resources\V1\User\ImageResource;
use App\Http\Resources\V1\User\OperatingHourResource;
use App\Http\Resources\V1\User\CourtResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'address'     => $this->address,
            'city'        => $this->city,

            // Statistik (Mengandalkan withAvg dan withCount di Controller)
            'rating_avg'    => round((float) ($this->reviews_avg_rating ?? 0), 1),
            'reviews_count' => (int) ($this->reviews_count ?? 0),

            // Relasi - Diarahkan ke sesama Resource User
            'images'          => ImageResource::collection($this->whenLoaded('images')),
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),
            'courts'          => CourtResource::collection($this->whenLoaded('courts')),

            // Logic Thumbnail
            'thumbnail' => $this->when($this->relationLoaded('images'), function () {
                $primary = $this->images->where('is_primary', true)->first();
                // Jika tidak ada yang primary, ambil foto pertama sebagai cadangan
                $image = $primary ?? $this->images->first();
                return $image ? new ImageResource($image) : null;
            }),

            // Metadata waktu (Biasanya User butuh info kapan venue ini gabung)
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
        ];
    }
}
