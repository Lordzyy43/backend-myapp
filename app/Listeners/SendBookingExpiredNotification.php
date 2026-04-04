<?php

namespace App\Listeners;

use App\Events\BookingExpired;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingExpiredNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public int $tries = 3;
  public int $backoff = 10;

  public function handle(BookingExpired $event): void
  {
    $booking = $event->booking;

    NotificationService::send(
      $booking->user_id,
      'booking_expired', // identifier notifikasi
      'Booking Kadaluarsa',
      'Booking kamu sudah kadaluarsa karena tidak diselesaikan tepat waktu',
      $booking
    );
  }

  public function failed(BookingExpired $event, \Throwable $exception): void
  {
    \Log::error('BookingExpired Notification Failed', [
      'booking_id' => $event->booking->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
