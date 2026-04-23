<?php

namespace App\Http\Resources\V1\Public;

use App\Http\Resources\V1\Shared\ImageResource;
use App\Http\Resources\V1\Shared\SportResource;
use App\Http\Resources\V1\Shared\MaintenanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_per_hour' => (float) $this->price_per_hour,
            'formatted_price' => 'Rp ' . number_format($this->price_per_hour, 0, ',', '.'),

            // Info Olahraga
            'sport' => new SportResource($this->whenLoaded('sport')),

            // Statistik
            'rating' => round($this->reviews()->avg('rating') ?? 0, 1),

            // Foto-foto khusus lapangan ini
            'images' => ImageResource::collection($this->whenLoaded('images')),

            // Status Ketersediaan Bisnis
            'is_active' => $this->isAvailable(),

            // Maintenance (Hanya tampilkan jika sedang ada perbaikan)
            'current_maintenance' => MaintenanceResource::collection(
                $this->whenLoaded('maintenances', function () {
                    return $this->maintenances()->where('start_date', '<=', now())
                        ->where('end_date', '>=', now())
                        ->get();
                })
            ),
        ];
    }
}
