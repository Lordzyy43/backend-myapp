<?php

namespace App\Listeners;

use App\Events\BookingFinished;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingFinishedNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public int $tries = 3;
  public int $backoff = 10;

  public function handle(BookingFinished $event): void
  {
    $booking = $event->booking;

    NotificationService::send(
      $booking->user_id,
      'booking_finished',
      'Booking Selesai',
      'Terima kasih telah menggunakan layanan kami',
      $booking
    );
  }

  public function failed(BookingFinished $event, \Throwable $exception): void
  {
    \Log::error('BookingFinished Notification Failed', [
      'booking_id' => $event->booking->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
