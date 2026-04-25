<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
  protected $midtransService;

  public function __construct(MidtransService $midtransService)
  {
    $this->midtransService = $midtransService;
  }

  public function handle(Request $request)
  {
    $notification = $this->midtransService->handleNotification();

    if (!$notification) {
      return response()->json(['message' => 'Invalid Notification'], 400);
    }

    // Midtrans mengirim order_id format: "BOOKINGCODE-TIMESTAMP"
    $orderIdParts = explode('-', $notification->order_id);
    $bookingCode = $orderIdParts[0];

    $transaction = $notification->transaction_status;
    $type = $notification->payment_type;
    $fraud = $notification->fraud_status;

    // Cari Payment dengan eager load booking & user (untuk notifikasi nanti)
    $payment = Payment::whereHas('booking', function ($q) use ($bookingCode) {
      $q->where('booking_code', $bookingCode);
    })->with('booking.user')->first();

    if (!$payment) {
      Log::warning("Midtrans Webhook: Payment not found for Booking Code $bookingCode");
      return response()->json(['message' => 'Payment not found'], 404);
    }

    try {
      return DB::transaction(function () use ($payment, $transaction, $type, $fraud, $notification) {

        // Re-lock row payment
        $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

        // Log aktivitas untuk debugging production
        Log::info("Processing Webhook for Booking: $payment->booking_id Status: $transaction");

        // 1. Success Logic (Pembayaran Berhasil)
        if ($transaction == 'capture') {
          if ($type == 'credit_card') {
            if ($fraud == 'challenge') {
              $payment->update(['payment_status_id' => Payment::PENDING]);
            } else {
              $payment->markAsPaid($notification->transaction_id, (array) $notification);
            }
          }
        } elseif ($transaction == 'settlement') {
          $payment->markAsPaid($notification->transaction_id, (array) $notification);
        }

        // 2. Pending Logic
        elseif ($transaction == 'pending') {
          $payment->update(['payment_status_id' => Payment::PENDING]);
        }

        // 3. Failure / Expiry Logic
        elseif (in_array($transaction, ['deny', 'expire', 'cancel'])) {
          // Gunakan method markAsFailed yang sudah kita buat di Model
          $payment->markAsFailed($transaction == 'expire' ? 'expired' : 'failed');
        }

        return response()->json(['message' => 'Webhook Processed Successfully']);
      });
    } catch (\Exception $e) {
      Log::error("Midtrans Webhook Error: " . $e->getMessage());
      return response()->json(['message' => 'Internal Server Error'], 500);
    }
  }
}
