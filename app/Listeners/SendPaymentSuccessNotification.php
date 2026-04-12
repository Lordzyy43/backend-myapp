<?php

namespace App\Listeners;

use App\Events\PaymentSuccess;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentSuccessNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(PaymentSuccess $event): void
    {
        $payment = $event->payment;

        if (!$payment->booking || !$payment->booking->user_id) {
            \Log::warning('PaymentSuccess: booking/user missing', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        NotificationService::send(
            userId: $payment->booking->user_id,
            type: 'payment_success',
            title: 'Pembayaran Berhasil',
            message: 'Pembayaran kamu berhasil untuk booking ' . ($payment->booking->booking_code ?? ''),
            notifiable: null,
            actionUrl: null,
            data: [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking->id ?? null,
                'booking_code' => $payment->booking->booking_code ?? null,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
                'status' => $payment->status->status_name ?? null,
            ]
        );
    }

    public function failed(PaymentSuccess $event, \Throwable $exception): void
    {
        \Log::error('PaymentSuccess Notification Failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
