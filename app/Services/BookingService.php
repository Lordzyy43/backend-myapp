<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingTimeSlot;
use App\Models\BookingStatus;
use App\Models\Court;
use App\Models\Promo;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService
{
  /**
   * 🔥 CREATE BOOKING
   */
  public function create($user, array $data): Booking
  {
    return DB::transaction(function () use ($user, $data) {

      // 🔥 ambil & lock slot
      $slots = TimeSlot::whereIn('id', $data['slot_ids'])
        ->lockForUpdate()
        ->get();

      if ($slots->count() !== count($data['slot_ids'])) {
        throw new Exception('Slot tidak valid');
      }

      // 🔥 anti double booking (race safe)
      if (BookingTimeSlot::isBooked(
        $data['court_id'],
        $data['booking_date'],
        $data['slot_ids']
      )) {
        throw new Exception('Slot sudah dibooking');
      }

      // 🔥 ambil price (fix aman)
      $court = Court::find($data['court_id']);
      $pricePerHour = optional($court)->price_per_hour;

      if (!$pricePerHour) {
        throw new Exception('Harga court tidak valid');
      }

      $total = $slots->count() * $pricePerHour;

      // 🔥 create booking
      $booking = Booking::create([
        'user_id' => $user->id,
        'court_id' => $data['court_id'],
        'booking_date' => $data['booking_date'],
        'status_id' => BookingStatus::pending(),
        'total_price' => $total,
      ]);

      // 🔥 attach slot
      foreach ($data['slot_ids'] as $slotId) {
        $booking->timeSlots()->attach($slotId, [
          'court_id' => $data['court_id'],
          'booking_date' => $data['booking_date'],
        ]);
      }

      // 🔥 promo (lock juga)
      if (!empty($data['promo_code'])) {
        $promo = Promo::where('promo_code', $data['promo_code'])
          ->lockForUpdate()
          ->first();

        if ($promo && $promo->isValid()) {
          $discount = $promo->calculateDiscount($booking->total_price);

          $booking->update([
            'total_price' => max(0, $booking->total_price - $discount)
          ]);

          $used = $promo->markUsed();
          if (!$used) {
            throw new Exception('Promo sudah tidak dapat digunakan');
          }
        }
      }

      // 🔥 expiry
      $booking->setExpiry();
      $booking->save();

      // 🔥 event
      event(new \App\Events\BookingCreated($booking));

      return $booking->load(['court', 'timeSlots', 'status']);
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

      if (!in_array($booking->status_id, [
        BookingStatus::pending(),
        BookingStatus::confirmed()
      ])) {
        throw new Exception('Booking tidak bisa dibatalkan');
      }

      if ($booking->booking_date < now()->toDateString()) {
        throw new Exception('Tidak bisa cancel booking yang sudah lewat');
      }

      $booking->update([
        'status_id' => BookingStatus::cancelled()
      ]);

      // 🔥 release slot
      $booking->timeSlots()->detach();

      event(new \App\Events\BookingRejected($booking));

      return $booking;
    });
  }

  /**
   * 🔥 APPROVE (ADMIN)
   */
  public function approve(Booking $booking): Booking
  {
    return DB::transaction(function () use ($booking) {

      $booking = $this->lockBooking($booking);

      if ($booking->status_id !== BookingStatus::pending()) {
        throw new Exception('Hanya booking pending');
      }

      if ($booking->isExpired()) {
        throw new Exception('Booking expired');
      }

      $booking->update([
        'status_id' => BookingStatus::confirmed()
      ]);

      event(new \App\Events\BookingApproved($booking));

      return $booking;
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
        throw new Exception('Harus confirmed');
      }

      if ($booking->booking_date > now()->toDateString()) {
        throw new Exception('Belum waktunya');
      }

      $booking->update([
        'status_id' => BookingStatus::finished()
      ]);

      event(new \App\Events\BookingFinished($booking));

      return $booking;
    });
  }
}
