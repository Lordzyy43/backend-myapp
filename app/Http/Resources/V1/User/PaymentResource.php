<?php

namespace App\Http\Resources\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'transaction_id' => $this->transaction_id,
            'method'         => strtoupper($this->payment_method ?? 'NOT_SELECTED'),

            // Format Harga
            'amount'           => (float) $this->amount,
            'formatted_amount' => 'Rp ' . number_format($this->amount, 0, ',', '.'),

            // Bukti Bayar (Manual TF)
            'payment_proof_url' => $this->payment_proof ? asset('storage/' . $this->payment_proof) : null,

            // Status yang UI-Friendly
            'status' => [
                'id'      => (int) $this->payment_status_id,
                'label'   => $this->status->name ?? 'Unknown',
                'color'   => $this->getStatusColor(),
                'is_paid' => $this->isPaid(),
            ],

            // Waktu & Kadaluarsa
            'paid_at'    => $this->paid_at ? $this->paid_at->toDateTimeString() : null,
            'expired_at' => $this->expired_at ? $this->expired_at->toDateTimeString() : null,
            'is_expired' => $this->isExpired(),

            // Timer Countdown untuk Frontend
            'expires_in_seconds' => ($this->expired_at && !$this->isPaid() && !$this->isExpired())
                ? max(0, now()->diffInSeconds($this->expired_at, false))
                : 0,

            // 🔥 Token Midtrans (Cek di kolom snap_token dulu, kalau kosong baru cek payload)
            'snap_token' => $this->snap_token ?? ($this->payload['snap_token'] ?? null),
            'snap_url'   => $this->snap_url ?? ($this->payload['snap_url'] ?? null),
        ];
    }

    /**
     * Helper Warna Status
     */
    private function getStatusColor(): string
    {
        return match ((int) $this->payment_status_id) {
            1 => 'warning', // Pending
            2 => 'success', // Paid
            3, 4, 5 => 'danger',  // Expired / Cancelled / Failed
            default => 'secondary',
        };
    }
}
