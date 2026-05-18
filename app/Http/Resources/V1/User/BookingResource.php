<?php

namespace App\Http\Resources\V1\User;

use App\Http\Resources\V1\User\CourtResource;
use App\Http\Resources\V1\User\SportResource;
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
            'user_id' => $this->user_id,
            'court_id' => $this->court_id,
            'booking_date' => $this->booking_date?->toDateString(),
            'status_id' => $this->status_id,
            'total_price' => (float) $this->total_price,
            'promo_code' => $this->promo_code,
            'discount' => (float) $this->discount,
            'discount_amount' => (float) $this->discount_amount,
            'discount_percentage' => (int) $this->discount_percentage_display,
            'final_price' => (float) $this->final_price,
            'expires_at' => $this->expires_at?->toDateTimeString(),

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
            'status' => [
                'id' => $this->status_id,
                'label' => $this->status->status_name ?? 'Unknown',
                'color' => $this->getStatusColor(),
            ],
            'time_slots' => $this->whenLoaded('timeSlots', function () {
                return $this->timeSlots->map(fn($slot) => [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'label' => $slot->label,
                ]);
            }),

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
        return match ((int) $this->status_id) {
            1 => 'warning', // Pending
            2 => 'success', // Approved / Paid
            3 => 'danger',  // Cancelled / Rejected
            4 => 'info',    // Finished
            default => 'secondary',
        };
    }
}
