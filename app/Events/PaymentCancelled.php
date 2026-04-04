<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCancelled
{
    use Dispatchable, SerializesModels;

    public Payment $payment;

    public function __construct(Payment $payment)
    {
        // 🔥 load relasi penting biar listener gak query ulang
        $this->payment = $payment->load([
            'booking.user',
            'status'
        ]);
    }
}
