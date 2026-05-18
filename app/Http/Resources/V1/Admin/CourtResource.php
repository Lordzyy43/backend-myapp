<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Admin\ImageResource;
use App\Http\Resources\V1\Admin\SportResource;
use App\Http\Resources\V1\Admin\MaintenanceResource;
use App\Http\Resources\V1\Admin\VenueResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'price_per_hour' => (float) $this->price_per_hour,
            'status'         => $this->status,
            'is_active'      => $this->status === 'active',

            // Relasi (Admin butuh info venue tempat lapangan ini berada)
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'sport' => new SportResource($this->whenLoaded('sport')),

            'images'           => ImageResource::collection($this->whenLoaded('images')),
            'all_maintenances' => MaintenanceResource::collection($this->whenLoaded('maintenances')),

            // Audit & Soft Delete Info
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toDateTimeString() : null,
        ];
    }
}
