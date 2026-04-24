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
    // 1. Ambil booking yang expired dengan relasi minimal
    Booking::where('status_id', BookingStatus::pending())
      ->where('expires_at', '<=', now())
      ->with(['payment', 'timeSlots']) // Load relasi di awal untuk efisiensi chunk
      ->chunkById(50, function ($bookings) {
        foreach ($bookings as $booking) {
          DB::transaction(function () use ($booking) {
            // Lock for update untuk keamanan race condition
            $booking = Booking::where('id', $booking->id)->lockForUpdate()->first();

            if (!$booking || $booking->status_id !== BookingStatus::pending()) return;

            // Simpan info lapangan dan tanggal untuk hapus cache nanti
            $courtId = $booking->court_id;
            $dateStr = \Carbon\Carbon::parse($booking->booking_date)->toDateString();

            // 🔥 1. Update Booking Status
            $booking->update(['status_id' => BookingStatus::expired()]);

            // 🔥 2. Release Slots
            $booking->timeSlots()->detach();

            // 🔥 3. Sync Payment
            if ($booking->payment && !$booking->payment->isPaid()) {
              $booking->payment->update([
                'payment_status_id' => PaymentStatus::expired(),
                'expired_at' => now()
              ]);
              event(new \App\Events\PaymentExpired($booking->payment));
            }

            // 🔥 4. CACHE CLEANUP (Kritikal!)
            // Supaya slot yang dilepas langsung muncul sebagai "Available" di aplikasi
            \Illuminate\Support\Facades\Cache::forget("availability_{$courtId}_{$dateStr}");

            event(new \App\Events\BookingExpired($booking));
            Log::info("Booking Auto-Expired: {$booking->booking_code}");
          });
        }
      });

    $this->info('Process completed: Pending bookings have been cleared.');
  }
}
