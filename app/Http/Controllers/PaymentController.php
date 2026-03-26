<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\PaymentStatus;
use App\Models\BookingStatus;

class PaymentController extends Controller
{
    /**
     * List payments (user only)
     */
    public function index()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $payments = Payment::with(['booking'])
                ->whereHas('booking', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data pembayaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PAY BOOKING (CORE 🔥)
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|string',
            'payment_proof' => 'nullable|string',
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        DB::beginTransaction();

        try {
            $booking = Booking::with('payment')->findOrFail($request->booking_id);

            /**
             * 🔥 VALIDATION LAYER (STRICT)
             */

            // ownership
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            // expired booking
            if ($booking->isExpired()) {
                return response()->json([
                    'message' => 'Booking sudah expired'
                ], 400);
            }

            // already paid / already has payment
            if ($booking->payment) {
                return response()->json([
                    'message' => 'Booking sudah memiliki pembayaran'
                ], 400);
            }

            // hanya boleh bayar jika status pending
            if ($booking->status_id !== BookingStatus::pending()) {
                return response()->json([
                    'message' => 'Booking tidak dalam status pending'
                ], 400);
            }

            /**
             * 🔥 CREATE PAYMENT (PENDING)
             */
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => $request->payment_method,
                'amount' => $booking->total_price,
                'payment_proof' => $request->payment_proof,
                'payment_status_id' => PaymentStatus::pending(),
            ]);

            /**
             * 🔥 SIMULASI SUCCESS (gateway nanti di sini)
             */
            $payment->update([
                'payment_status_id' => PaymentStatus::paid(),
                'paid_at' => now(),
            ]);

            /**
             * 🔥 UPDATE BOOKING → CONFIRMED
             */
            $booking->update([
                'status_id' => BookingStatus::confirmed(),
                'expires_at' => null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Pembayaran berhasil',
                'data' => $payment->load('booking')
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Booking tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses pembayaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show detail payment
     */
    public function show(string $id)
    {
        try {
            $payment = Payment::with('booking')->findOrFail($id);

            if ($payment->booking->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            return response()->json([
                'message' => 'Success',
                'data' => $payment
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Payment tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel payment (BEFORE PAID)
     */
    public function destroy(string $id)
    {
        try {
            $payment = Payment::with('booking')->findOrFail($id);

            if ($payment->booking->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            // 🔥 hanya bisa cancel kalau masih pending
            if ($payment->payment_status_id === PaymentStatus::paid()) {
                return response()->json([
                    'message' => 'Tidak bisa membatalkan pembayaran yang sudah berhasil'
                ], 400);
            }

            DB::beginTransaction();

            // update payment → failed
            $payment->update([
                'payment_status_id' => PaymentStatus::failed()
            ]);

            // update booking → cancelled
            $payment->booking->update([
                'status_id' => BookingStatus::cancelled()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Pembayaran dibatalkan'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Payment tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membatalkan pembayaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
