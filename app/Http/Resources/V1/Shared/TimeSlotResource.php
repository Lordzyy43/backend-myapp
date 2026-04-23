<?php

namespace App\Http\Resources\V1\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_time' => substr($this->start_time, 0, 5), // Output: "08:00"
            'end_time' => substr($this->end_time, 0, 5),   // Output: "09:00"

            // Label gabungan untuk mempermudah UI (Misal di Dropdown atau Card)
            'display_label' => $this->label ?? (substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5)),

            'order_index' => (int) $this->order_index,
            'duration_minutes' => $this->getDurationMinutes(), // Mengambil dari helper di Model

            /**
             * Status Ketersediaan (Dynamic)
             * Kita gunakan whenHas agar field ini hanya muncul jika Controller 
             * melakukan pengecekan availability (is_available di-append secara manual).
             */
            'is_available' => $this->whenHas('is_available'),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
