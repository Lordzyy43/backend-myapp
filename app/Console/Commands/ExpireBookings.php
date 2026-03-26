<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\BookingStatus;

class ExpireBookings extends Command
{
  // Nama command yang bakal dipanggil via cron: php artisan booking:expire
  protected $signature = 'booking:expire';
  protected $description = 'Auto expire pending bookings and release slots';

  public function handle()
  {
    // Ambil semua booking pending yang udah melewati waktu expires_at
    $expiredBookings = Booking::where('status_id', BookingStatus::pending())
      ->where('expires_at', '<=', now())
      ->get();

    if ($expiredBookings->isEmpty()) {
      $this->info('No pending bookings to expire.');
      return;
    }

    foreach ($expiredBookings as $booking) {
      // Update status ke expired
      $booking->status_id = BookingStatus::expired();
      $booking->save();

      // Release slot supaya bisa dibooking orang lain
      $booking->timeSlots()->detach();
    }

    $this->info('Expired bookings processed: ' . $expiredBookings->count());
  }
}
