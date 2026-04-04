<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\PaymentStatus;
use App\Models\BookingStatus;

use App\Http\Requests\StorePaymentRequest;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    /**
     * LIST PAYMENT
     */
    public function index()
    {
        $user = auth()->user();

        $payments = Payment::with(['booking', 'status'])
            ->whereHas('booking', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(10);

        return $this->success(
            $payments->items(),
            'List payment berhasil diambil',
            200,
            [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ]
        );
    }

    /**
     * CREATE PAYMENT
     */
    public function store(StorePaymentRequest $request)
    {
        try {
            $validated = $request->validated();

            $booking = Booking::findOrFail($validated['booking_id']);

            $this->authorize('pay', $booking);

            if ($booking->isExpired()) {
                return $this->error('Booking sudah expired', [], 400);
            }

            if ($booking->payment) {
                return $this->error('Payment sudah ada', [], 400);
            }

            if ($booking->status_id !== BookingStatus::pending()) {
                return $this->error('Booking tidak valid', [], 400);
            }

            $payment = DB::transaction(function () use ($validated, $booking) {
                return Payment::create([
                    'booking_id' => $booking->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => $booking->total_price,
                    'payment_status_id' => PaymentStatus::pending(),
                    'expired_at' => $booking->expires_at,
                ]);
            });

            return $this->created(
                $payment->load(['booking', 'status']),
                'Payment berhasil dibuat'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Booking tidak ditemukan');
        } catch (\Exception $e) {
            return $this->error('Terjadi kesalahan server', [], 500);
        }
    }

    /**
     * SHOW PAYMENT
     */
    public function show($id)
    {
        try {
            $payment = Payment::with(['booking', 'status'])
                ->findOrFail($id);

            $this->authorize('view', $payment);

            return $this->success(
                $payment,
                'Detail payment berhasil diambil'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Payment tidak ditemukan');
        } catch (\Exception $e) {
            return $this->error('Terjadi kesalahan server', [], 500);
        }
    }

    /**
     * CANCEL PAYMENT
     */
    public function cancel($id)
    {
        try {
            $payment = Payment::findOrFail($id);

            // 1. Authorize dulu (cek di PaymentPolicy@cancel)
            $this->authorize('cancel', $payment);

            // 2. Business Rule: Jangan cancel yang sudah dibayar
            if ($payment->payment_status_id === PaymentStatus::paid()) {
                return $this->error('Tidak bisa cancel payment yang sudah dibayar', [], 400);
            }

            // 3. Update Status dalam transaction
            DB::transaction(function () use ($payment) {
                $payment->update([
                    'payment_status_id' => PaymentStatus::cancelled()
                ]);

                // Update booking status & release slots
                $payment->booking->update([
                    'status_id' => BookingStatus::cancelled()
                ]);

                $payment->booking->timeSlots()->detach();

                event(new \App\Events\PaymentCancelled($payment));
            });

            return $this->success(null, 'Payment berhasil dibatalkan');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden();
        } catch (\Exception $e) {
            return $this->error('Terjadi kesalahan server', [], 500);
        }
    }
}
