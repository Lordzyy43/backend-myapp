<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Admin\ImageResource;
use App\Http\Resources\V1\Admin\SportResource;
use App\Http\Resources\V1\Admin\MaintenanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venue_id' => $this->venue_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_per_hour' => (float) $this->price_per_hour,
            'status' => $this->status, // misal: 'active', 'inactive', 'maintenance'

            // Relasi
            'sport' => new SportResource($this->whenLoaded('sport')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'all_maintenances' => MaintenanceResource::collection($this->whenLoaded('maintenances')),

            // Info Audit
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toDateTimeString() : null,
        ];
    }
}
