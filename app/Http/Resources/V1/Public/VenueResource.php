<?php

namespace App\Http\Resources\V1\Public;

use App\Http\Resources\V1\Shared\ImageResource;
use App\Http\Resources\V1\Shared\OperatingHourResource;
use App\Http\Resources\V1\Public\CourtResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'slug'  => $this->slug,
            'description' => $this->description,
            'address'     => $this->address,
            'city'        => $this->city,

            // Statistik (Gunakan aggregate yang sudah di-load agar cepat)
            'rating_avg'    => round($this->reviews_avg_rating ?? 0, 1),
            'reviews_count' => $this->reviews_count ?? 0,

            // Relasi - Gunakan 'whenLoaded' agar tidak error jika relasi tidak dipanggil
            'images'          => ImageResource::collection($this->whenLoaded('images')),
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),
            'courts'          => CourtResource::collection($this->whenLoaded('courts')),

            // Thumbnail (Logic simpel untuk gambar utama)
            'thumbnail' => $this->when($this->relationLoaded('images'), function () {
                $primary = $this->images->where('is_primary', true)->first();
                return $primary ? new ImageResource($primary) : null;
            }),
        ];
    }
}
