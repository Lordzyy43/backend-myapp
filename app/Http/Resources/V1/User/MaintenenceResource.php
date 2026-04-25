<?php

namespace App\Http\Resources\V1\User;

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

      // Tanggal Rentang Maintenance
      'start_date' => $this->start_date->toDateString(),
      'end_date'   => $this->end_date->toDateString(),

      // Format yang enak dibaca di UI (Contoh: 26 Apr 2026)
      'formatted_start' => $this->start_date->format('d M Y'),
      'formatted_end'   => $this->end_date->format('d M Y'),

      // Alasan (Bisa ditampilkan di tooltip atau warning card)
      'reason' => $this->reason,

      // Status bantu untuk Frontend
      'is_ongoing' => now()->between($this->start_date, $this->end_date->endOfDay()),
    ];
  }
}
