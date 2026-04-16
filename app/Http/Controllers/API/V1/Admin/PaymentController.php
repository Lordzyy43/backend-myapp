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
        try {
            $payment = Payment::findOrFail($id);

            // 🔥 AUTH DULU (biar test admin lolos)
            $this->authorize('approve', $payment);

            // 🔥 BUSINESS RULE
            if ($payment->isPaid()) {
                return $this->error('Payment sudah dibayar', [], 400);
            }

            if ($payment->isExpired()) {
                return $this->error('Payment sudah expired', [], 400);
            }

            $payment = DB::transaction(function () use ($payment) {

                $payment->refresh();

                $payment->update([
                    'payment_status_id' => PaymentStatus::paid(),
                    'paid_at' => now()
                ]);

                $payment->booking->update([
                    'status_id' => BookingStatus::confirmed(),
                    'expires_at' => null
                ]);

                return $payment->load(['booking', 'status']);
            });

            event(new \App\Events\PaymentSuccess($payment));

            return $this->success($payment, 'Payment berhasil di-approve');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden('Forbidden access. Admin only.', [
                'role' => ['You do not have the required admin role.']
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Payment tidak ditemukan');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [], 400);
        }
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
