<?php

namespace App\Listeners;

use App\Events\BookingApproved;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingApprovedNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public int $tries = 3;
  public int $backoff = 10;

  public function handle(BookingApproved $event): void
  {
    $booking = $event->booking;

    NotificationService::send(
      $booking->user_id,
      'booking_approved',
      'Booking Disetujui',
      'Booking kamu telah disetujui',
      $booking
    );
  }

  public function failed(BookingApproved $event, \Throwable $exception): void
  {
    \Log::error('BookingApproved Notification Failed', [
      'booking_id' => $event->booking->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
