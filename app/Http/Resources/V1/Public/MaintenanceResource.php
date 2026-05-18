<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'date_range' => $this->start_date->format('d M') . ' - ' . $this->end_date->format('d M Y'),
            'reason' => $this->reason ?? 'Maintenance Rutin',
            'is_ongoing' => now()->between($this->start_date, $this->end_date->endOfDay()),
            'days_remaining' => now()->lessThan($this->end_date)
                ? (int) now()->diffInDays($this->end_date->endOfDay())
                : 0,
        ];
    }
}
