<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTimeSlot;
use App\Models\BookingStatus;
use App\Models\PaymentStatus;
use App\Models\Court;
use App\Models\Promo;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Exception;

use function Symfony\Component\Clock\now;

class BookingService
{
  /**
   * 🔥 CREATE BOOKING
   */
  public function store(array $data): Booking
  {
    $user = auth()->user();

    return DB::transaction(function () use ($user, $data) {
      // 1. Validasi Slot & Lock
      $slots = TimeSlot::whereIn('id', $data['slot_ids'])->lockForUpdate()->get();
      if ($slots->count() !== count($data['slot_ids'])) {
        throw new \Exception('Slot tidak valid');
      }

      // 2. Anti Double Booking
      if (BookingTimeSlot::isBooked($data['court_id'], $data['booking_date'], $data['slot_ids'])) {
        throw \Illuminate\Validation\ValidationException::withMessages([
          'slot_ids' => ['Slot sudah dibooking']
        ]);
      }

      // 3. Hitung Harga
      $court = Court::findOrFail($data['court_id']);
      $pricePerHour = $court->price_per_hour ?? 0;
      if ($pricePerHour <= 0) throw new \Exception('Harga court tidak valid');

      $total = $slots->count() * $pricePerHour;

      // 4. Inisialisasi Variabel Promo
      $discount = 0;
      $promoCode = null;
      $discountPercentage = 0;

      // 5. Cek Promo
      if (!empty($data['promo_code'])) {
        $promo = Promo::whereRaw('LOWER(promo_code) = ?', [strtolower($data['promo_code'])])
          ->lockForUpdate()->first();

        if (!$promo) {
          throw \Illuminate\Validation\ValidationException::withMessages(['promo_code' => ['Invalid promo code']]);
        }
        if (!$promo->is_active) {
          throw \Illuminate\Validation\ValidationException::withMessages(['promo_code' => ['Promo code is not active']]);
        }

        $today = \Carbon\Carbon::today()->format('Y-m-d');
        if ($promo->start_date->format('Y-m-d') > $today || $promo->end_date->format('Y-m-d') < $today) {
          throw \Illuminate\Validation\ValidationException::withMessages(['promo_code' => ['Promo code has expired']]);
        }

        if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
          throw \Illuminate\Validation\ValidationException::withMessages(['promo_code' => ['Promo code usage limit exceeded']]);
        }

        $discount = $promo->calculateDiscount($total);
        $promoCode = $promo->promo_code;

        if ($promo->discount_type === 'percentage') {
          $discountPercentage = $promo->discount_value;
        }

        $promo->markUsed();
      }

      // 6. Buat Booking
      $booking = Booking::create([
        'booking_code'        => 'BK-' . strtoupper(\Illuminate\Support\Str::random(8)),
        'user_id'             => $user->id,
        'court_id'            => $data['court_id'],
        'booking_date'        => $data['booking_date'],
        'status_id'           => BookingStatus::pending(),
        'total_price'         => $total,
        'promo_code'          => $promoCode,
        'discount'            => $discount,
        'discount_percentage' => $discountPercentage,
        'final_price'         => max(0, $total - $discount),
      ]);

      // 7. Attach Slot
      foreach ($data['slot_ids'] as $slotId) {
        $booking->timeSlots()->attach($slotId, [
          'court_id'     => $data['court_id'],
          'booking_date' => $data['booking_date'],
        ]);
      }

      // 8. Finalisasi & Set Expiry
      $booking->setExpiry();
      $booking->save();

      // 🔥 SOLUSI IDEAL: Hapus Cache Availability agar Test baris 587 IJO
      // Kita gunakan format date yang konsisten dengan AvailabilityController
      $dateStr = \Carbon\Carbon::parse($data['booking_date'])->toDateString();
      $cacheKey = "availability_{$data['court_id']}_{$dateStr}";
      \Illuminate\Support\Facades\Cache::forget($cacheKey);

      // 9. Trigger Event untuk Notifikasi
      event(new \App\Events\BookingCreated($booking));

      return $booking;
    });
  }

  /**
   * 🔥 Lock booking utility
   */
  protected function lockBooking(Booking $booking): Booking
  {
    return Booking::where('id', $booking->id)
      ->lockForUpdate()
      ->firstOrFail();
  }

  /**
   * 🔥 REJECT BOOKING (ADMIN)
   */
  public function reject(Booking $booking): Booking
  {
    return DB::transaction(function () use ($booking) {

      $booking = $this->lockBooking($booking);

      if ($booking->status_id !== BookingStatus::pending()) {
        throw new Exception('Hanya booking pending yang bisa di-reject');
      }

      $booking->update([
        'status_id' => BookingStatus::cancelled()
      ]);

      // 🔥 release slot
      $booking->timeSlots()->detach();

      event(new \App\Events\BookingRejected($booking));

      return $booking->load(['court', 'timeSlots', 'status']);
    });
  }

  /**
   * 🔥 CANCEL BOOKING (USER)
   */

  public function cancel(Booking $booking): Booking
  {
    return DB::transaction(function () use ($booking) {
      $booking = $this->lockBooking($booking);

      // 1. Guard Check
      if ($booking->status_id == BookingStatus::finished()) {
        throw new \Exception('Tidak bisa cancel booking yang sudah selesai');
      }

      // 2. Prepare IDs for Debugging
      $cancelledStatusId = BookingStatus::cancelled();
      $cancelledPaymentId = PaymentStatus::where('status_name', 'cancelled')->value('id');

      // Log sebelum eksekusi
      \Log::info("Attempting cancel: Booking ID {$booking->id}. Target Booking Status: {$cancelledStatusId}, Target Payment Status: {$cancelledPaymentId}");

      // 3. Update status booking
      $booking->update([
        'status_id' => $cancelledStatusId
      ]);

      // 4. Update status payment
      if ($booking->payment) {
        $booking->payment->update([
          'payment_status_id' => $cancelledPaymentId
        ]);
      }

      // 5. Detach
      $booking->timeSlots()->detach();

      // 6. Refresh & Verify
      $booking->refresh();

      // 🔥 DEBUG CHECK: Jika status tidak sesuai, log ini akan sangat membantu
      if ($booking->status_id != $cancelledStatusId) {
        \Log::error("DEBUG ERROR: Status mismatch! Expected {$cancelledStatusId}, Got {$booking->status_id}");
      }

      if ($booking->payment && $booking->payment->payment_status_id != $cancelledPaymentId) {
        \Log::error("DEBUG ERROR: Payment Status mismatch! Expected {$cancelledPaymentId}, Got {$booking->payment->payment_status_id}");
      }

      // 7. Event Dispatch
      event(new \App\Events\BookingCancelled($booking));

      return $booking;
    });
  }
  /**
   * 🔥 APPROVE (ADMIN)
   */
  public function approve(Booking $booking): Booking
  {
    // Pastikan pakai return di depan DB::transaction
    return DB::transaction(function () use ($booking) {
      try {
        $booking = $this->lockBooking($booking);

        $allowedStatuses = [
          \App\Models\BookingStatus::pending()
        ];

        if (!in_array($booking->status_id, $allowedStatuses)) {
          throw new \Exception('Hanya booking pending atau sudah dikonfirmasi pembayaran yang bisa di-approve');
        }

        if ($booking->isExpired()) {
          throw new \Exception('Booking ini sudah expired');
        }

        $booking->update([
          'status_id' => \App\Models\BookingStatus::confirmed(),
          'approved_at' => now(),
          'approved_by' => auth()->id(),
        ]);

        $dateStr = \Carbon\Carbon::parse($booking->booking_date)->toDateString();
        $cacheKey = "availability_{$booking->court_id}_{$dateStr}";
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        event(new \App\Events\BookingApproved($booking->fresh(['user', 'court', 'status'])));

        return $booking;
      } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("Approve Booking Failed: " . $e->getMessage());
        throw $e;
      }
    });
  }

  /**
   * 🔥 FINISH (ADMIN)
   */
  public function finish(Booking $booking): Booking
  {
    return DB::transaction(function () use ($booking) {
      $booking = $this->lockBooking($booking);

      if ($booking->status_id !== BookingStatus::confirmed()) {
        throw new \Exception('Hanya booking dengan status Confirmed yang bisa diselesaikan.');
      }

      $lastSlot = $booking->timeSlots()->orderBy('end_time', 'desc')->first();

      // 1. Waktu sekarang (terikat dengan travelTo() di Testing)
      $now = \Carbon\Carbon::now();

      // 2. Waktu selesai (berdasarkan tanggal booking dan end_time slot terakhir)
      $finishDateTime = \Carbon\Carbon::parse(
        $booking->booking_date->format('Y-m-d') . ' ' . $lastSlot->end_time
      )->timezone(config('app.timezone'));

      // 3. Validasi: Booking hanya bisa diselesaikan setelah waktu selesai slot
      if ($now->lessThan($finishDateTime)) {
        throw new \Exception("Belum waktunya. Booking ini baru selesai pada jam {$lastSlot->end_time}");
      }

      $booking->update([
        'status_id' => BookingStatus::finished()
      ]);

      event(new \App\Events\BookingFinished($booking));

      return $booking;
    });
  }

  // Utility untuk mengirim error validasi dengan format yang konsisten

  protected function abortValidation($field, $message)
  {
    throw \Illuminate\Validation\ValidationException::withMessages([
      $field => [$message],
    ]);
  }
}
