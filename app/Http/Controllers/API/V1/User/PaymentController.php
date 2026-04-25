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
use App\Http\Resources\V1\User\PaymentResource; // Import Resource
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    /**
     * LIST PAYMENT (USER)
     */
    public function index()
    {
        $user = auth()->user();

        $payments = Payment::with(['booking', 'status'])
            ->whereHas('booking', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(10);

        // Langsung kirim collection Resource, Base Controller akan urus Meta-nya
        return $this->success(
            PaymentResource::collection($payments),
            'List payment berhasil diambil'
        );
    }

    /**
     * CREATE PAYMENT
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            $validated = $request->validated();
            $booking = Booking::with(['user', 'court'])->findOrFail($validated['booking_id']);

            $this->authorize('pay', $booking);

            if ($booking->isExpired()) {
                return $this->error('Booking sudah expired', [], 400);
            }

            // Gunakan konstanta yang ada di Model/Status kamu
            if ($booking->status_id != 1) { // 1 = Pending
                return $this->forbidden('Booking tidak bisa dibayar');
            }

            // Reuse existing pending payment
            $existingPayment = Payment::where('booking_id', $booking->id)
                ->where('payment_status_id', Payment::PENDING)
                ->first();

            if ($existingPayment && $existingPayment->snap_token) {
                return $this->success(
                    new PaymentResource($existingPayment),
                    'Gunakan token pembayaran yang sudah ada'
                );
            }

            $payment = DB::transaction(function () use ($booking) {
                // Asumsi MidtransService kamu sudah oke
                $midtransService = new \App\Services\MidtransService();
                $midtransRes = $midtransService->createTransaction($booking);

                if (!$midtransRes) {
                    throw new \Exception("Gagal menginisialisasi pembayaran ke Midtrans");
                }

                return Payment::create([
                    'booking_id'        => $booking->id,
                    'payment_method'    => 'midtrans',
                    'amount'            => $booking->final_price,
                    'payment_status_id' => Payment::PENDING,
                    'snap_token'        => $midtransRes['token'],
                    'snap_url'          => $midtransRes['redirect_url'],
                    'expired_at'        => $booking->expires_at,
                ]);
            });

            return $this->created(
                new PaymentResource($payment->load(['booking', 'status'])),
                'Payment berhasil dibuat, silakan selesaikan pembayaran.'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Payment Store Error: " . $e->getMessage());
            return $this->error($e->getMessage(), [], 500);
        }
    }

    /**
     * SHOW PAYMENT
     */
    public function show($id)
    {
        $payment = Payment::with(['booking', 'status'])->findOrFail($id);
        $this->authorize('view', $payment);

        return $this->success(new PaymentResource($payment), 'Detail payment berhasil diambil');
    }

    /**
     * CANCEL PAYMENT (Oleh User)
     */
    public function cancel($id)
    {
        $payment = Payment::findOrFail($id);
        $this->authorize('cancel', $payment);

        if ($payment->isPaid()) {
            return $this->error('Tidak bisa cancel payment yang sudah dibayar', [], 400);
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['payment_status_id' => Payment::CANCELLED]);

            $payment->booking->update(['status_id' => 3]); // 3 = Cancelled
            $payment->booking->timeSlots()->detach();

            event(new \App\Events\PaymentCancelled($payment));
        });

        return $this->success(null, 'Payment berhasil dibatalkan');
    }
}
