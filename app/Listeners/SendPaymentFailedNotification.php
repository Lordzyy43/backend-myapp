<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;
        $booking = $payment->booking;

        if (!$booking || !$booking->user_id) {
            \Log::warning('PaymentFailed: booking/user missing', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        NotificationService::send(
            $booking->user_id,
            type: 'payment_failed',
            title: 'Pembayaran Gagal',
            message: 'Pembayaran kamu gagal untuk booking ' . $booking->booking_code,
            payload: [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'amount' => $payment->amount,

                // 🔥 timing (lebih akurat dari now)
                'failed_at' => $payment->updated_at ?? now(),

                // 🔥 status info
                'status' => $payment->status->status_name ?? null,

                // 🔥 future gateway ready
                'transaction_id' => $payment->transaction_id,
                'method' => $payment->payment_method,
            ]
        );
    }

    public function failed(PaymentFailed $event, \Throwable $exception): void
    {
        \Log::error('PaymentFailed Notification Failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
