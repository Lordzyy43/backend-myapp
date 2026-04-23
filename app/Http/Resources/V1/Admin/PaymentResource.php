<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'method' => strtoupper($this->payment_method),
            'amount' => (float) $this->amount,

            // Status
            'status' => [
                'id' => $this->payment_status_id,
                'label' => $this->status->name ?? 'Unknown',
                'is_paid' => $this->isPaid(),
            ],

            // Bukti Bayar & Data Teknis
            'payment_proof_url' => $this->payment_proof ? asset('storage/' . $this->payment_proof) : null,
            'raw_payload' => $this->payload, // Full data dari Payment Gateway

            // Waktu
            'expired_at' => $this->expired_at ? $this->expired_at->toDateTimeString() : null,
            'paid_at' => $this->paid_at ? $this->paid_at->toDateTimeString() : null,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
