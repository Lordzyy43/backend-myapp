<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireBookings extends Command
{
  protected $signature = 'booking:expire';
  protected $description = 'Auto expire pending bookings + sync payment + release slot';

  public function handle()
  {
    Booking::where('status_id', BookingStatus::pending())
      ->where('expires_at', '<=', now())
      ->chunkById(50, function ($bookings) {

        foreach ($bookings as $booking) {

          DB::transaction(function () use ($booking) {

            // 🔥 lock row langsung
            $booking = Booking::lockForUpdate()
              ->with(['payment', 'timeSlots'])
              ->find($booking->id);

            if (!$booking || $booking->status_id !== BookingStatus::pending()) {
              return;
            }

            // 🔥 update booking
            $booking->update([
              'status_id' => BookingStatus::expired()
            ]);

            // 🔥 release slot
            $booking->timeSlots()->detach();

            // 🔥 sync payment
            if ($booking->payment && !$booking->payment->isPaid()) {

              $booking->payment->update([
                'payment_status_id' => PaymentStatus::expired(),
                'expired_at' => now()
              ]);

              event(new \App\Events\PaymentExpired($booking->payment));
            }

            // 🔥 booking event
            event(new \App\Events\BookingExpired($booking));

            Log::info("Booking expired: {$booking->id}");
          });
        }
      });

    $this->info('Expire process completed');
  }
}
