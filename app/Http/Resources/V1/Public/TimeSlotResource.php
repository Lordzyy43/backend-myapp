<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class TimeSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Menggunakan Carbon agar format waktu lebih konsisten dibanding substr
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time'   => Carbon::parse($this->end_time)->format('H:i'),

            'display_label' => $this->label ?? (Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i')),

            'order_index'      => (int) $this->order_index,
            'duration_minutes' => $this->getDurationMinutes(),

            // Status ketersediaan dinamis (hanya muncul jika dicek via AvailabilityController)
            'is_available' => $this->when(isset($this->is_available), (bool) $this->is_available),

            // Public hanya perlu tahu kalau slot ini aktif secara sistem
            'is_active' => (bool) $this->is_active,
        ];
    }
}
