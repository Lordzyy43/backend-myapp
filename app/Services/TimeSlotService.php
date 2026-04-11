<?php

namespace App\Services;

use App\Models\TimeSlot;
use App\Models\BookingTimeSlot;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * TimeSlotService
 * Handles time slot operations:
 * - Checking availability
 * - Booking time slots
 * - Managing time slot availability across courts
 */
class TimeSlotService
{
  /**
   * Get available time slots for a court on a specific date
   *
   * @param int $courtId
   * @param \DateTime $date
   * @return Collection
   */
  public function getAvailableTimeSlots(int $courtId, \DateTime $date): Collection
  {
    // Get all time slots for the court
    $allTimeSlots = TimeSlot::where('court_id', $courtId)
      ->where('is_active', true)
      ->get();

    // Get booked time slots for the date
    $bookedTimeSlots = BookingTimeSlot::whereHas('booking', function ($query) use ($date) {
      $query->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['pending', 'approved']);
    })
      ->pluck('time_slot_id')
      ->toArray();

    // Filter available slots
    return $allTimeSlots->filter(fn($slot) => !in_array($slot->id, $bookedTimeSlots));
  }

  /**
   * Check if a time slot is available for a specific date
   *
   * @param int $timeSlotId
   * @param int $courtId
   * @param \DateTime $date
   * @return bool
   */
  public function isTimeSlotAvailable(int $timeSlotId, int $courtId, \DateTime $date): bool
  {
    // Check if time slot exists and belongs to court
    $timeSlot = TimeSlot::where('id', $timeSlotId)
      ->where('court_id', $courtId)
      ->where('is_active', true)
      ->first();

    if (!$timeSlot) {
      return false;
    }

    // Check if already booked
    $isBooked = BookingTimeSlot::whereHas('booking', function ($query) use ($date) {
      $query->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['pending', 'approved']);
    })
      ->where('time_slot_id', $timeSlotId)
      ->exists();

    return !$isBooked;
  }

  /**
   * Book time slots for a booking
   *
   * @param Booking $booking
   * @param array $timeSlotIds
   * @return Collection
   * @throws \Exception
   */
  public function bookTimeSlots(Booking $booking, array $timeSlotIds): Collection
  {
    return DB::transaction(function () use ($booking, $timeSlotIds) {
      $bookingDate = $booking->booking_date;
      $courtId = $booking->court_id;
      $bookedSlots = collect();

      foreach ($timeSlotIds as $timeSlotId) {
        // Verify time slot availability
        if (!$this->isTimeSlotAvailable($timeSlotId, $courtId, $bookingDate)) {
          throw new \Exception("Time slot {$timeSlotId} is not available");
        }

        // Create booking time slot
        $bookingTimeSlot = BookingTimeSlot::create([
          'booking_id' => $booking->id,
          'time_slot_id' => $timeSlotId,
        ]);

        $bookedSlots->push($bookingTimeSlot);
      }

      Log::info("Time slots booked", [
        'booking_id' => $booking->id,
        'slot_count' => count($timeSlotIds),
        'booking_date' => $bookingDate,
      ]);

      return $bookedSlots;
    });
  }

  /**
   * Release time slots from a booking
   *
   * @param Booking $booking
   * @return int Number of slots released
   */
  public function releaseTimeSlots(Booking $booking): int
  {
    return DB::transaction(function () use ($booking) {
      $released = BookingTimeSlot::where('booking_id', $booking->id)->delete();

      Log::info("Time slots released", [
        'booking_id' => $booking->id,
        'released_count' => $released,
      ]);

      return $released;
    });
  }

  /**
   * Get consecutive available time slots
   *
   * @param int $courtId
   * @param \DateTime $date
   * @param int $consecutiveCount Number of consecutive slots needed
   * @return array Array of consecutive slot combinations
   */
  public function getConsecutiveAvailableSlots(int $courtId, \DateTime $date, int $consecutiveCount = 2): array
  {
    $availableSlots = $this->getAvailableTimeSlots($courtId, $date)
      ->sortBy('start_time')
      ->values()
      ->toArray();

    if (count($availableSlots) < $consecutiveCount) {
      return [];
    }

    $consecutiveSlots = [];

    for ($i = 0; $i <= count($availableSlots) - $consecutiveCount; $i++) {
      $combination = [];
      $isConsecutive = true;

      for ($j = 0; $j < $consecutiveCount; $j++) {
        $current = $availableSlots[$i + $j];
        $next = $availableSlots[$i + $j + 1] ?? null;

        $combination[] = $current;

        // Check if slots are consecutive (no gap between end and next start)
        if ($next && $current->end_time !== $next->start_time) {
          $isConsecutive = false;
          break;
        }
      }

      if ($isConsecutive) {
        $consecutiveSlots[] = $combination;
      }
    }

    return $consecutiveSlots;
  }

  /**
   * Get court's operating hours as time slots for a specific day
   *
   * @param int $courtId
   * @param string $dayOfWeek (0-6, 0 = Sunday)
   * @return Collection
   */
  public function getOperatingHoursForDay(int $courtId, string $dayOfWeek): Collection
  {
    return TimeSlot::where('court_id', $courtId)
      ->where('day_of_week', $dayOfWeek)
      ->where('is_active', true)
      ->orderBy('start_time')
      ->get();
  }

  /**
   * Create time slots for a court based on operating hours
   *
   * @param int $courtId
   * @param array $operatingHours Format: ['monday' => ['open' => '08:00', 'close' => '22:00'], ...]
   * @param int $slotDurationMinutes
   * @return Collection
   */
  public function generateTimeSlots(int $courtId, array $operatingHours, int $slotDurationMinutes = 60): Collection
  {
    return DB::transaction(function () use ($courtId, $operatingHours, $slotDurationMinutes) {
      $generatedSlots = collect();
      $daysMap = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
      ];

      foreach ($operatingHours as $day => $hours) {
        $dayOfWeek = $daysMap[$day] ?? null;
        if (!$dayOfWeek) {
          continue;
        }

        $startTime = \Carbon\Carbon::createFromFormat('H:i', $hours['open']);
        $endTime = \Carbon\Carbon::createFromFormat('H:i', $hours['close']);

        while ($startTime < $endTime) {
          $slotEndTime = $startTime->copy()->addMinutes($slotDurationMinutes);

          if ($slotEndTime > $endTime) {
            break;
          }

          $slot = TimeSlot::create([
            'court_id' => $courtId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $slotEndTime->format('H:i:s'),
            'is_active' => true,
          ]);

          $generatedSlots->push($slot);
          $startTime = $slotEndTime;
        }
      }

      Log::info("Time slots generated for court", [
        'court_id' => $courtId,
        'slot_count' => $generatedSlots->count(),
      ]);

      return $generatedSlots;
    });
  }

  /**
   * Get booked time slots for a booking
   *
   * @param Booking $booking
   * @return Collection
   */
  public function getBookedTimeSlots(Booking $booking): Collection
  {
    return BookingTimeSlot::where('booking_id', $booking->id)
      ->with('timeSlot')
      ->get()
      ->pluck('timeSlot');
  }

  /**
   * Calculate total duration for booked time slots
   *
   * @param Booking $booking
   * @return int Duration in minutes
   */
  public function calculateBookingDuration(Booking $booking): int
  {
    $slots = $this->getBookedTimeSlots($booking);

    if ($slots->isEmpty()) {
      return 0;
    }

    $totalMinutes = 0;

    foreach ($slots as $slot) {
      $start = \Carbon\Carbon::createFromFormat('H:i:s', $slot->start_time);
      $end = \Carbon\Carbon::createFromFormat('H:i:s', $slot->end_time);
      $totalMinutes += $start->diffInMinutes($end);
    }

    return $totalMinutes;
  }

  /**
   * Get price per slot duration
   *
   * @param int $courtId
   * @return float Price per hour
   */
  public function getSlotPrice(int $courtId): float
  {
    // This should return the court's base price
    // You may need to adjust based on your court pricing model
    return DB::table('courts')
      ->where('id', $courtId)
      ->value('price_per_hour') ?? 0;
  }

  /**
   * Check for time slot overlap
   *
   * @param int $courtId
   * @param \DateTime $date
   * @param string $startTime
   * @param string $endTime
   * @param int|null $excludeBookingId
   * @return bool
   */
  public function hasTimeSlotOverlap(
    int $courtId,
    \DateTime $date,
    string $startTime,
    string $endTime,
    ?int $excludeBookingId = null
  ): bool {
    $query = BookingTimeSlot::whereHas('booking', function ($q) use ($courtId, $date) {
      $q->where('court_id', $courtId)
        ->where('booking_date', $date->format('Y-m-d'))
        ->whereIn('status', ['pending', 'approved']);
    })
      ->whereHas('timeSlot', function ($q) use ($startTime, $endTime) {
        $q->where(function ($query) use ($startTime, $endTime) {
          $query->whereBetween('start_time', [$startTime, $endTime])
            ->orWhereBetween('end_time', [$startTime, $endTime])
            ->orWhere(function ($q) use ($startTime, $endTime) {
              $q->where('start_time', '<=', $startTime)
                ->where('end_time', '>=', $endTime);
            });
        });
      });

    if ($excludeBookingId) {
      $query->where('booking_id', '!=', $excludeBookingId);
    }

    return $query->exists();
  }
}
