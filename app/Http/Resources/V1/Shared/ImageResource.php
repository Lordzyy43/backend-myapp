<?php

namespace App\Http\Resources\V1\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'is_primary' => (bool) $this->is_primary, // Kita paksa jadi boolean
        ];
    }
}
