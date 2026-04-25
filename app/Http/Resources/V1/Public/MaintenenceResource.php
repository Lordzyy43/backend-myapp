<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class MaintenanceResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   */
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'reason' => $this->reason, // Alasan perbaikan (misal: Ganti Rumput)

      // Format tanggal agar enak dibaca di Frontend (01 Jan 2026)
      'start_date' => $this->start_date->format('d M Y'),
      'end_date'   => $this->end_date->format('d M Y'),

      // Tambahan info buat Frontend: Berapa hari lagi selesai?
      'remaining_days' => $this->end_date->diffInDays(now()),

      // Status kemudahan (jika hari ini masuk rentang maintenance)
      'is_ongoing' => now()->between($this->start_date, $this->end_date),
    ];
  }
}
