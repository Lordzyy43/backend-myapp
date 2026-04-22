<?php

namespace App\Http\Resources\V1\User;

use App\Http\Resources\V1\Public\CourtResource;
use App\Http\Resources\V1\Shared\SportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,

            // Info Lapangan & Olahraga
            'court' => new CourtResource($this->whenLoaded('court')),
            'sport' => new SportResource($this->whenLoaded('sport')),

            /**
             * Evolusi: Schedule Details
             * Dibuat seperti ini agar Frontend gampang bikin card per jam
             * tapi tetap punya info range waktu secara keseluruhan.
             */
            'schedule' => [
                'date' => $this->booking_date,
                'total_hours' => $this->whenLoaded('timeSlots', function () {
                    return $this->timeSlots->count() . ' Jam';
                }),
                // Ringkasan untuk teks (misal: "08:00 - 10:00")
                'display_range' => $this->whenLoaded('timeSlots', function () {
                    $start = substr($this->timeSlots->min('start_time'), 0, 5);
                    $end = substr($this->timeSlots->max('end_time'), 0, 5);
                    return $start . ' - ' . $end;
                }),
                // List Atomic untuk Card-Card per jam di UI
                'slots' => $this->whenLoaded('timeSlots', function () {
                    return $this->timeSlots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'time_label' => substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5),
                            'price' => (float) $slot->pivot->price_at_booking ?? (float) $slot->price,
                        ];
                    });
                }),
            ],

            // Status & Pembayaran
            'total_price' => (float) $this->total_price,
            'status' => [
                'id' => $this->booking_status_id,
                'label' => $this->status->name ?? 'Unknown',
                'color' => $this->getStatusColor(),
            ],

            // Payment Info
            'payment' => new PaymentResource($this->whenLoaded('payment')),

            // Metadata Waktu
            'created_at_human' => $this->created_at->diffForHumans(),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    /**
     * Helper untuk menentukan warna status (UI-Friendly)
     */
    private function getStatusColor(): string
    {
        return match ($this->booking_status_id) {
            1 => 'warning', // Pending
            2 => 'success', // Approved / Paid
            3 => 'danger',  // Cancelled / Rejected
            4 => 'info',    // Finished
            default => 'secondary',
        };
    }
}
