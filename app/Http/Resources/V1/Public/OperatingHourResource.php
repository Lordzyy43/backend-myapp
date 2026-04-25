<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class OperatingHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Sesuai model: day_of_week
            'day' => $this->day_of_week,
            // Gunakan Carbon agar lebih aman daripada substr
            'open_time'  => $this->open_time ? Carbon::parse($this->open_time)->format('H:i') : null,
            'close_time' => $this->close_time ? Carbon::parse($this->close_time)->format('H:i') : null,
            // Jika di database tidak ada field is_closed, kita bisa anggap 
            // jika open_time null maka tutup. Tapi di sini saya ikuti defaultnya:
            'is_closed' => (bool) ($this->is_closed ?? false),
        ];
    }
}
