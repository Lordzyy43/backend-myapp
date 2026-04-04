<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        NotificationService::send(
            $booking->user_id,
            'booking_created',
            'Booking Dibuat',
            'Silakan lanjutkan pembayaran',
            $booking
        );
    }

    public function failed(BookingCreated $event, \Throwable $exception): void
    {
        \Log::error('BookingCreated Notification Failed', [
            'booking_id' => $event->booking->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
