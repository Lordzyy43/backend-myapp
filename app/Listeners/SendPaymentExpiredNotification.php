<?php

namespace App\Listeners;

use App\Events\PaymentExpired;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentExpiredNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(PaymentExpired $event): void
    {
        $payment = $event->payment;
        $booking = $payment->booking;

        if (!$booking || !$booking->user_id) {
            \Log::warning('PaymentExpired: booking/user missing', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        NotificationService::send(
            userId: $booking->user_id,
            type: 'payment_expired',
            title: 'Pembayaran Kadaluarsa',
            message: "Pembayaran untuk booking {$booking->booking_code} telah habis",
            data: [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'amount' => $payment->amount,

                // 🔥 source of truth
                'expired_at' => $payment->expired_at,

                'status' => $payment->status->status_name ?? null,

                // 🔥 optional tapi bagus
                'transaction_id' => $payment->transaction_id,
                'method' => $payment->payment_method,
            ]
        );
    }

    public function failed(PaymentExpired $event, \Throwable $exception): void
    {
        \Log::error('PaymentExpired Notification Failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
