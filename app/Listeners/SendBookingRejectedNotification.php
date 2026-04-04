<?php

namespace App\Listeners;

use App\Events\BookingRejected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingRejectedNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public int $tries = 3;
  public int $backoff = 10;

  public function handle(BookingRejected $event): void
  {
    $booking = $event->booking;

    NotificationService::send(
      $booking->user_id,
      'booking_rejected',
      'Booking Ditolak',
      'Booking kamu ditolak oleh admin',
      $booking
    );
  }

  public function failed(BookingRejected $event, \Throwable $exception): void
  {
    \Log::error('BookingRejected Notification Failed', [
      'booking_id' => $event->booking->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
