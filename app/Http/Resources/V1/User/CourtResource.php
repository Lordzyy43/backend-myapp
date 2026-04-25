<?php

namespace App\Http\Resources\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'price_per_hour'  => (float) $this->price_per_hour,
            'formatted_price' => 'Rp ' . number_format($this->price_per_hour, 0, ',', '.'),

            // Memanggil relasi sesuai struktur folder User kamu
            'venue'  => new VenueResource($this->whenLoaded('venue')),
            'sport'  => new SportResource($this->whenLoaded('sport')),
            'images' => ImageResource::collection($this->whenLoaded('images')),

            // Rating (Ambil dari aggregate reviews_avg_rating jika dipanggil withAvg di controller)
            'rating' => round($this->reviews_avg_rating ?? 0, 1),

            'status'    => $this->status,
            'is_active' => $this->status === 'active',

            // Maintenance: Menampilkan yang sedang berjalan saja
            'current_maintenance' => $this->when($this->relationLoaded('maintenances'), function () {
                $now = now();
                $activeMaintenance = $this->maintenances->filter(function ($m) use ($now) {
                    return $m->start_date <= $now && $m->end_date >= $now;
                });
                return MaintenanceResource::collection($activeMaintenance);
            }),
        ];
    }
}
