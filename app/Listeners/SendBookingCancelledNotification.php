<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingCancelledNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public int $tries = 3;
  public int $backoff = 10;

  public function handle(BookingCancelled $event): void
  {
    $booking = $event->booking;

    NotificationService::send(
      $booking->user_id,
      'booking_cancelled',
      'Booking Dibatalkan',
      'Booking kamu telah dibatalkan',
      $booking
    );
  }

  public function failed(BookingCancelled $event, \Throwable $exception): void
  {
    \Log::error('BookingCancelled Notification Failed', [
      'booking_id' => $event->booking->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
