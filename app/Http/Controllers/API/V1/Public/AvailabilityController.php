<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\{TimeSlot, BookingTimeSlot, Court, VenueOperatingHour, CourtMaintenance, BookingStatus};
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date',
        ]);

        $court = Court::with('venue')->findOrFail($validated['court_id']);
        $date = Carbon::parse($validated['date']);
        $dateStr = $date->toDateString();
        $today = Carbon::today();
        $now = Carbon::now();

        // 1. Operating Hours Check
        $operating = VenueOperatingHour::where('venue_id', $court->venue_id)
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (!$operating || $operating->is_closed) {
            return $this->success(['slots' => [], 'meta' => ['is_closed' => true, 'is_maintenance' => false]], 'Venue tutup');
        }

        // 2. Maintenance Check
        $isMaintenance = CourtMaintenance::where('court_id', $court->id)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->exists();

        $cacheKey = "availability_{$court->id}_{$dateStr}";

        // Logic Utama (Tetap menggunakan closure aslimu)
        $fetchLogic = function () use ($court, $date, $dateStr, $today, $now, $isMaintenance, $operating) {

            $bookedSlotIds = BookingTimeSlot::where('court_id', $court->id)
                ->where('booking_date', $dateStr)
                ->whereHas('booking', function ($q) {
                    $q->whereIn('status_id', [BookingStatus::pending(), BookingStatus::confirmed()]);
                })
                ->pluck('time_slot_id')
                ->toArray();

            // 🔥 OPTIMASI: Hanya ambil slot yang masuk dalam jam operasional via Query
            $slots = TimeSlot::active()
                ->whereTime('start_time', '>=', $operating->open_time)
                ->whereTime('end_time', '<=', $operating->close_time)
                ->orderBy('order_index')
                ->get();

            return $slots->map(function ($slot) use ($bookedSlotIds, $date, $today, $now, $isMaintenance) {
                $reason = null;
                $slotId = (int)$slot->id;

                $sStart = Carbon::parse($slot->start_time);
                $sEnd = Carbon::parse($slot->end_time);

                // URUTAN PRIORITAS
                if ($isMaintenance) {
                    $reason = 'maintenance';
                } elseif (in_array($slotId, $bookedSlotIds)) {
                    $reason = 'booked';
                } elseif ($date->isToday() && $sStart->copy()->setDateFrom($date)->lt($now)) {
                    $reason = 'passed';
                }

                return [
                    'id' => $slotId,
                    'label' => $slot->label ?? ($sStart->format('H:i') . ' - ' . $sEnd->format('H:i')),
                    'start_time' => $sStart->format('H:i'),
                    'end_time' => $sEnd->format('H:i'),
                    'is_available' => is_null($reason),
                    'reason' => $reason,
                ];
            });
        };

        // Simpan di cache selama 5 menit untuk performa maksimal
        $result = Cache::remember($cacheKey, now()->addMinutes(5), $fetchLogic);

        return $this->success([
            'slots' => $result,
            'meta' => [
                'is_closed' => false,
                'is_maintenance' => $isMaintenance,
            ]
        ], 'Availability berhasil diambil');
    }
}
