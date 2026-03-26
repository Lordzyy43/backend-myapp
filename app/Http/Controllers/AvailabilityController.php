<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeSlot;
use App\Models\BookingTimeSlot;
use App\Models\Booking;
use App\Models\Court;
use App\Models\VenueOperatingHour;
use App\Models\CourtMaintenance;
use App\Models\BookingStatus;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'court_id' => 'required|exists:courts,id',
                'date' => 'required|date',
            ]);

            $court = Court::with('venue')->findOrFail($request->court_id);

            $date = Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeek;
            $today = Carbon::today();
            $now = Carbon::now();

            /**
             * 🔥 1. OPERATING HOURS
             */
            $operating = VenueOperatingHour::where('venue_id', $court->venue_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (!$operating) {
                return response()->json([
                    'message' => 'Venue tutup di hari ini',
                    'data' => []
                ]);
            }

            $openTime = Carbon::parse($operating->open_time);
            $closeTime = Carbon::parse($operating->close_time);

            /**
             * 🔥 2. MAINTENANCE RANGE
             */
            $maintenances = CourtMaintenance::where('court_id', $court->id)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->get();

            /**
             * 🔥 3. SLOT
             */
            $slots = TimeSlot::active()
                ->orderBy('start_time')
                ->get();

            /**
             * 🔥 4. VALID BOOKINGS
             */
            $validBookings = Booking::where('court_id', $court->id)
                ->where('booking_date', $date->toDateString())
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->whereIn('status_id', [
                    BookingStatus::pending(),
                    BookingStatus::confirmed()
                ])
                ->pluck('id');

            $bookedSlotIds = BookingTimeSlot::whereIn('booking_id', $validBookings)
                ->pluck('time_slot_id')
                ->toArray();

            /**
             * 🔥 5. FINAL MAPPING (SMART)
             */
            $result = $slots->map(function ($slot) use (
                $bookedSlotIds,
                $openTime,
                $closeTime,
                $date,
                $today,
                $now,
                $maintenances
            ) {

                $slotStart = Carbon::parse($slot->start_time);
                $slotEnd = Carbon::parse($slot->end_time);

                $reason = null;

                // 🔥 booked
                if (in_array($slot->id, $bookedSlotIds)) {
                    $reason = 'booked';
                }

                // 🔥 outside operating hours
                if (!$reason && ($slotStart < $openTime || $slotEnd > $closeTime)) {
                    $reason = 'outside_operating_hours';
                }

                // 🔥 past time
                if (!$reason && $date->isSameDay($today) && $slotStart < $now) {
                    $reason = 'past_time';
                }

                // 🔥 maintenance
                if (!$reason && $maintenances->isNotEmpty()) {
                    $reason = 'maintenance';
                }

                return [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'label' => $slot->label ?? ($slot->start_time . ' - ' . $slot->end_time),
                    'is_available' => $reason === null,
                    'reason' => $reason, // 🔥 ini penting
                ];
            });

            return response()->json([
                'message' => 'Success',
                'data' => $result
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
