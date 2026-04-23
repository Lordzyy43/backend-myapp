<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\Shared\TimeSlotResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,

            // Info Pelanggan (Penting untuk Admin)
            'customer' => [
                'id' => $this->user_id,
                'name' => $this->user->name ?? 'N/A',
                'phone' => $this->user->phone ?? 'N/A',
            ],

            // Detail Pesanan
            'venue_name' => $this->court->venue->name ?? 'N/A',
            'court_name' => $this->court->name ?? 'N/A',
            'booking_date' => $this->booking_date,
            'slots' => TimeSlotResource::collection($this->whenLoaded('timeSlots')),

            // Status Finansial
            'total_price' => (float) $this->total_price,
            'status' => [
                'id' => $this->booking_status_id,
                'label' => $this->status->name ?? 'Unknown',
            ],

            // Relasi Pembayaran
            'payment' => new PaymentResource($this->whenLoaded('payment')),

            // Audit
            'created_at' => $this->created_at->toDateTimeString(),
            'expires_at' => $this->expires_at ? $this->expires_at->toDateTimeString() : null,
        ];
    }
}
