<?php

namespace App\Listeners;

use App\Events\PaymentCancelled;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentCancelledNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(PaymentCancelled $event): void
    {
        $payment = $event->payment;
        // Gunakan eager loading jika belum, tapi di sini kita asumsikan sudah ada
        $booking = $payment->booking;

        if (!$booking || !$booking->user_id) {
            \Log::warning('PaymentCancelled: booking/user missing', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        NotificationService::send(
            userId: $booking->user_id,
            type: 'payment_cancelled',
            title: 'Pembayaran Dibatalkan',
            message: 'Pembayaran dibatalkan untuk booking ' . $booking->booking_code,
            notifiable: $payment,
            actionUrl: null,
            data: [ // Ganti nama key agar sesuai dengan $data di service jika ingin pakai named param
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'amount' => $payment->amount,
                'cancelled_at' => $payment->updated_at ?? now(),
                'status' => $payment->status->status_name ?? 'cancelled',
                'transaction_id' => $payment->transaction_id,
                'method' => $payment->payment_method,
            ]
        );
    }

    public function failed(PaymentCancelled $event, \Throwable $exception): void
    {
        \Log::error('PaymentCancelled Notification Failed', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
