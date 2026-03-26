<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\BookingTimeSlot;
use App\Models\BookingStatus;
use App\Models\Promo;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings (role-based, exclude expired)
     */
    public function index()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 🔥 FILTER: hanya tampil booking aktif
            $bookings = Booking::with(['court', 'timeSlots', 'status'])
                ->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $bookings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new booking (CORE LOGIC 🔥)
     */
    public function store(Request $request)
    {
        $request->validate([
            'court_id' => 'required|exists:courts,id',
            'booking_date' => 'required|date',
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'exists:time_slots,id',
            'promo_code' => 'nullable|string', // optional promo
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        DB::beginTransaction();
        try {
            // 🔥 SLOT VALIDATION (anti double-book)
            if (BookingTimeSlot::isBooked($request->court_id, $request->booking_date, $request->slot_ids)) {
                return response()->json(['message' => 'Slot sudah dibooking'], 409);
            }

            // 🔥 CREATE BOOKING
            $booking = Booking::create([
                'user_id' => $user->id,
                'court_id' => $request->court_id,
                'booking_date' => $request->booking_date,
                'status_id' => BookingStatus::pending(),
            ]);

            // 🔥 HITUNG TOTAL
            $booking->total_price = $booking->calculateTotalPrice($request->slot_ids);

            // 🔥 APPLY PROMO
            if ($request->filled('promo_code')) {
                $promo = Promo::where('promo_code', $request->promo_code)->first();
                if ($promo && $promo->isValid()) {
                    $discount = $promo->calculateDiscount($booking->total_price);
                    $booking->total_price -= $discount;
                    if ($booking->total_price < 0) $booking->total_price = 0;
                    $promo->markUsed();
                }
            }

            // 🔥 SET EXPIRY
            $booking->setExpiry();
            $booking->save();

            // 🔥 ATTACH SLOT
            $booking->timeSlots()->attach($request->slot_ids);

            DB::commit();

            return response()->json([
                'message' => 'Booking berhasil',
                'data' => $booking->load('timeSlots', 'status')
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($e->getCode() == 23000) { // race condition
                return response()->json(['message' => 'Slot sudah diambil user lain'], 409);
            }
            return response()->json(['message' => 'Database error', 'error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan saat booking', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show single booking
     */
    public function show(string $id)
    {
        try {
            $booking = Booking::with(['court', 'timeSlots', 'status'])->findOrFail($id);

            // 🔥 protect data
            if ($booking->user_id !== auth()->id()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            return response()->json([
                'message' => 'Success',
                'data' => $booking
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil data', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel booking
     */
    public function destroy(string $id)
    {
        try {
            $booking = Booking::findOrFail($id);

            if ($booking->user_id !== auth()->id()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // 🔥 UPDATE STATUS CANCELLED
            $booking->status_id = BookingStatus::cancelled();
            $booking->save();

            // 🔥 RELEASE SLOT
            $booking->timeSlots()->detach();

            return response()->json(['message' => 'Booking berhasil dibatalkan']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membatalkan booking', 'error' => $e->getMessage()], 500);
        }
    }
}
