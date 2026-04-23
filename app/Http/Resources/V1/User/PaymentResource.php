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
            'id' => $this->id,
            'transaction_id' => $this->transaction_id, // ID dari Gateway atau Kode Unik
            'method' => strtoupper($this->payment_method ?? 'NOT_SELECTED'),

            // Format Harga
            'amount' => (float) $this->amount,
            'formatted_amount' => 'Rp ' . number_format($this->amount, 0, ',', '.'),

            // Bukti Bayar (Jika sistemnya upload manual/TF)
            'payment_proof_url' => $this->payment_proof ? asset('storage/' . $this->payment_proof) : null,

            // Status yang UI-Friendly
            'status' => [
                'id' => $this->payment_status_id,
                'label' => $this->status->name ?? 'Unknown',
                'color' => $this->getStatusColor(),
                'is_paid' => $this->isPaid(),
            ],

            // Waktu & Kadaluarsa
            'paid_at' => $this->paid_at ? $this->paid_at->toDateTimeString() : null,
            'expired_at' => $this->expired_at ? $this->expired_at->toDateTimeString() : null,
            'is_expired' => $this->isExpired(),

            // Timer dalam detik untuk Frontend (Countdown)
            'expires_in_seconds' => ($this->expired_at && !$this->isPaid())
                ? max(0, now()->diffInSeconds($this->expired_at, false))
                : 0,

            // Payload bisa dikirim sebagian jika Frontend butuh Snap Token Midtrans
            'snap_token' => $this->payload['snap_token'] ?? null,
        ];
    }

    /**
     * Helper Warna Status
     */
    private function getStatusColor(): string
    {
        return match ($this->payment_status_id) {
            1 => 'warning', // Pending / Waiting
            2 => 'success', // Paid
            3 => 'danger',  // Expired / Failed / Cancelled
            default => 'secondary',
        };
    }
}
