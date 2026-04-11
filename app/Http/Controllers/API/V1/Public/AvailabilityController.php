<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\TimeSlot;
use App\Models\BookingTimeSlot;
use App\Models\Court;
use App\Models\VenueOperatingHour;
use App\Models\CourtMaintenance;
use App\Models\BookingStatus;
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

        // 1. Operating Hours
        $operating = VenueOperatingHour::where('venue_id', $court->venue_id)
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (!$operating) {
            return $this->success(['slots' => [], 'meta' => ['is_closed' => true, 'is_maintenance' => false]], 'Venue tutup');
        }

        // 2. Maintenance
        $isMaintenance = CourtMaintenance::where('court_id', $court->id)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->exists();

        $cacheKey = "availability_{$court->id}_{$dateStr}";

        // Logic Utama dalam Closure agar bisa dipakai Cache atau Direct
        $fetchLogic = function () use ($court, $date, $dateStr, $today, $now, $isMaintenance, $operating) {

            // Ambil ID slot yang sudah terisi
            $bookedSlotIds = BookingTimeSlot::where('court_id', $court->id)
                ->where('booking_date', $dateStr)
                ->whereHas('booking', function ($q) {
                    $q->whereIn('status_id', [
                        BookingStatus::pending(),
                        BookingStatus::confirmed(),
                    ]);
                })
                ->pluck('time_slot_id')
                // ->map(fn($id) => (int)$id)
                ->toArray();

            $slots = TimeSlot::active()->orderBy('order_index')->get();

            return $slots->map(function ($slot) use ($bookedSlotIds, $operating, $date, $today, $now, $isMaintenance) {
                $reason = null;
                $slotId = (int)$slot->id;

                $sStart = Carbon::parse($slot->start_time);
                $sEnd = Carbon::parse($slot->end_time);
                $oOpen = Carbon::parse($operating->open_time);
                $oClose = Carbon::parse($operating->close_time);

                // URUTAN PRIORITAS (PENTING!)
                if (in_array($slotId, $bookedSlotIds)) {
                    $reason = 'booked';
                } elseif ($date->isSameDay($today) && $sStart->lt($now)) {
                    $reason = 'past_time';
                } elseif ($sStart->format('H:i') < $oOpen->format('H:i') || $sEnd->format('H:i') > $oClose->format('H:i')) {
                    $reason = 'outside_operating_hours';
                } elseif ($isMaintenance) {
                    $reason = 'maintenance';
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

        // Jalankan Cache (Hanya bypass flush jika di test tertentu, tapi biarkan Cache::remember tetap jalan agar test cache tidak fail)
        $result = Cache::remember($cacheKey, now()->addMinutes(1), $fetchLogic);

        return $this->success([
            'slots' => $result,
            'meta' => [
                'is_closed' => false,
                'is_maintenance' => $isMaintenance,
            ]
        ], 'Availability berhasil diambil');
    }
}
