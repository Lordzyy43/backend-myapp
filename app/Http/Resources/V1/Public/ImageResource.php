<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Kita panggil image_url karena Model sudah punya Accessor-nya
            'url' => $this->image_url,
            // Kita gunakan is_primary jika ada (khusus VenueImage biasanya ada)
            'is_primary' => (bool) ($this->is_primary ?? false),
        ];
    }
}
