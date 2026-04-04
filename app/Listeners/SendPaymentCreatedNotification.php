<?php

namespace App\Listeners;

use App\Events\PaymentCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(PaymentCreated $event): void
    {
        $payment = $event->payment;
        $booking = $payment->booking;

        if (!$booking || !$booking->user_id) {
            \Log::warning('PaymentCreated: booking/user missing', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        NotificationService::send(
            $booking->user_id,
            type: 'payment_created',
            title: 'Menunggu Pembayaran',
            message: 'Silakan selesaikan pembayaran untuk booking ' . $booking->booking_code,
            payload: [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'amount' => $payment->amount,

                // 🔥 ini sudah benar (source of truth)
                'expired_at' => $payment->expired_at,

                'status' => $payment->status->status_name ?? null,

                // 🔥 penting untuk gateway
                'transaction_id' => $payment->transaction_id,
                'method' => $payment->payment_method,
            ]
        );
    }

    public function failed(PaymentCreated $event, \Throwable $exception): void
    {
        \Log::error('PaymentCreated Notification Failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
