<?php

namespace App\Http\Resources\V1\Public;

use App\Http\Resources\V1\Public\ImageResource;
use App\Http\Resources\V1\Public\SportResource;
use App\Http\Resources\V1\Public\MaintenanceResource;
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

            // Info Venue (Ditambahkan agar user tahu ini lapangan milik siapa)
            'venue' => new VenueResource($this->whenLoaded('venue')),

            // Info Olahraga
            'sport' => new SportResource($this->whenLoaded('sport')),

            // Statistik
            'rating' => round($this->reviews_avg_rating ?? $this->average_rating ?? 0, 1),
            'average_rating' => round($this->reviews_avg_rating ?? $this->average_rating ?? 0, 2),
            'review_count' => (int) ($this->reviews_count ?? $this->review_count ?? 0),

            // Foto-foto khusus lapangan ini
            'images' => ImageResource::collection($this->whenLoaded('images')),

            // Status Ketersediaan Bisnis
            'status' => $this->status, // Menampilkan status asli (active/inactive)
            'is_active' => $this->status === 'active',

            // Maintenance (Logika tetap sama, tapi dioptimalkan pemanggilannya)
            'current_maintenance' => $this->when($this->relationLoaded('maintenances'), function () {
                $now = now();
                $activeMaintenance = $this->maintenances
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
                return MaintenanceResource::collection($activeMaintenance);
            }),
        ];
    }
}
