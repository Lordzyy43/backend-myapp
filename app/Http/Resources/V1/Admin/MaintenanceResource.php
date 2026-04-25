<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,

            // Format Tanggal yang bersih
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),

            // Label untuk UI (Misal: "22 Apr - 25 Apr")
            'date_range' => $this->start_date->format('d M') . ' - ' . $this->end_date->format('d M Y'),

            'reason' => $this->reason ?? 'Maintenance Rutin',

            // Helper untuk Frontend: Status aktif saat ini
            'is_ongoing' => now()->between($this->start_date, $this->end_date->endOfDay()),

            // Berapa hari lagi maintenance selesai
            'days_remaining' => now()->lessThan($this->end_date)
                ? (int) now()->diffInDays($this->end_date->endOfDay())
                : 0,
        ];
    }
}
