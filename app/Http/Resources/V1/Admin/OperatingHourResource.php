<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatingHourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'day' => $this->day, // misal: "Monday"
            'open_time' => substr($this->open_time, 0, 5), // Potong detik (08:00:00 -> 08:00)
            'close_time' => substr($this->close_time, 0, 5),
            'is_closed' => (bool) $this->is_closed,
        ];
    }
}
