<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\BookingStatus;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    /**
     * LIST PAYMENT (ADMIN)
     */
    public function index(Request $request)
    {
        $query = Payment::with(['booking.user', 'status']);

        if ($request->filled('status_id')) {
            $query->where('payment_status_id', $request->status_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $payments = $query->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'List payment berhasil diambil',
            'data' => $payments
        ]);
    }

    /**
     * SHOW DETAIL
     */
    public function show($id)
    {
        $payment = Payment::with(['booking.user', 'status'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail payment berhasil diambil',
            'data' => $payment
        ]);
    }

    /**
     * 🔥 APPROVE PAYMENT (FIX FINAL)
     */
    public function approve($id)
    {
        $payment = Payment::findOrFail($id);

        // 🔥 1. BUSINESS RULE DULU (WAJIB)
        if ($payment->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment sudah dibayar'
            ], 400);
        }

        if ($payment->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment sudah expired'
            ], 400);
        }

        // 🔥 2. AUTH SETELAH VALIDASI
        $this->authorize('approve', $payment);

        // 🔥 3. TRANSACTION
        $payment = DB::transaction(function () use ($payment) {

            $payment->refresh(); // biar sync

            $now = now()->toDateTimeString(); // 🔥 FIX TEST (timestamp exact)

            $payment->update([
                'payment_status_id' => PaymentStatus::paid(),
                'paid_at' => $now
            ]);

            $payment->booking->update([
                'status_id' => BookingStatus::confirmed(),
                'expires_at' => null
            ]);

            return $payment->load(['booking', 'status']);
        });

        event(new \App\Events\PaymentSuccess($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment berhasil di-approve',
            'data' => $payment
        ]);
    }

    /**
     * REJECT PAYMENT
     */
    public function reject($id)
    {
        $payment = DB::transaction(function () use ($id) {

            $payment = Payment::lockForUpdate()->findOrFail($id);

            if ($payment->isPaid()) {
                abort(400, 'Tidak bisa reject payment yang sudah dibayar');
            }

            $payment->update([
                'payment_status_id' => PaymentStatus::failed()
            ]);

            $payment->booking->update([
                'status_id' => BookingStatus::cancelled()
            ]);

            return $payment->load(['booking', 'status']);
        });

        event(new \App\Events\PaymentFailed($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment berhasil ditolak',
            'data' => $payment
        ]);
    }

    /**
     * FORCE EXPIRE
     */
    public function expire($id)
    {
        $payment = DB::transaction(function () use ($id) {

            $payment = Payment::lockForUpdate()->findOrFail($id);

            if ($payment->isPaid()) {
                abort(400, 'Payment sudah dibayar');
            }

            $payment->update([
                'payment_status_id' => PaymentStatus::expired(),
                'expired_at' => now()
            ]);

            $payment->booking->update([
                'status_id' => BookingStatus::expired()
            ]);

            return $payment->load(['booking', 'status']);
        });

        event(new \App\Events\PaymentExpired($payment));

        return response()->json([
            'success' => true,
            'message' => 'Payment berhasil di-expire',
            'data' => $payment
        ]);
    }
}