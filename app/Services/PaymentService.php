<?php

namespace App\Services;

use App\Constants\PaymentConstants;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PaymentService
 * Handles all payment-related business logic including:
 * - Payment creation and validation
 * - Payment status transitions
 * - Refund processing
 * - Payment expiry checks
 */
class PaymentService
{
  /**
   * Create a new payment record for a booking
   *
   * @param Booking $booking The booking to create payment for
   * @param array $data Payment data (transaction_id, payment_method, etc)
   * @return Payment
   * @throws \Exception
   */
  public function createPayment(Booking $booking, array $data): Payment
  {
    return DB::transaction(function () use ($booking, $data) {

      // 🔥 LOCK BOOKING
      $booking = Booking::where('id', $booking->id)->lockForUpdate()->first();

      // 🔥 VALIDASI STATUS BOOKING
      if ($booking->status_id !== BookingStatus::pending()) {
        throw ValidationException::withMessages([
          'payment' => ['Payment can only be created for pending booking']
        ]);
      }

      // 🔥 CEK EXISTING PAYMENT
      $existingPayment = Payment::where('booking_id', $booking->id)
        ->whereIn('status_id', [
          PaymentStatus::pending(),
          PaymentStatus::paid()
        ])
        ->lockForUpdate()
        ->first();

      if ($existingPayment) {
        throw ValidationException::withMessages([
          'payment' => ['Payment already exists']
        ]);
      }

      return Payment::create([
        'booking_id' => $booking->id,
        'status_id' => PaymentStatus::pending(),
        'amount' => $booking->final_price ?? $booking->total_price,
        'payment_method' => $data['payment_method'] ?? 'bank_transfer',
        'transaction_id' => $data['transaction_id'] ?? null,
        'payment_proof' => $data['payment_proof'] ?? null,
        'expires_at' => now()->addMinutes(PaymentConstants::PAYMENT_EXPIRY_MINUTES),
        'verified_at' => null,
      ]);
    });
  }

  /**
   * Confirm/verify a payment after user confirmation
   *
   * @param Payment $payment
   * @param array $verificationData
   * @return Payment
   * @throws \Exception
   */
  public function confirmPayment(Payment $payment, array $verificationData = []): Payment
  {
    return DB::transaction(function () use ($payment, $verificationData) {

      $payment = Payment::where('id', $payment->id)
        ->lockForUpdate()
        ->with('booking')
        ->first();

      // 🔥 EXPIRED CHECK
      if ($payment->isExpired()) {
        $payment->update(['status_id' => PaymentStatus::expired()]);

        throw ValidationException::withMessages([
          'payment' => ['Payment has expired']
        ]);
      }

      // 🔥 STATUS CHECK
      if ($payment->status_id !== PaymentStatus::pending()) {
        throw ValidationException::withMessages([
          'payment' => ['Payment cannot be processed']
        ]);
      }

      $payment->update([
        'status_id' => PaymentStatus::paid(),
        'verified_at' => now(),
        'verification_data' => $verificationData,
      ]);

      // 🔥 UPDATE BOOKING
      $payment->booking->update([
        'status_id' => BookingStatus::confirmed(),
      ]);

      return $payment;
    });
  }

  /**
   * Cancel a payment
   *
   * @param Payment $payment
   * @param string $reason Reason for cancellation
   * @return Payment
   */
  public function cancelPayment(Payment $payment, string $reason = ''): Payment
  {
    return DB::transaction(function () use ($payment, $reason) {

      $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

      if ($payment->status_id !== PaymentStatus::pending()) {
        throw ValidationException::withMessages([
          'payment' => ['Payment cannot be cancelled']
        ]);
      }

      $payment->update([
        'status_id' => PaymentStatus::cancelled(),
        'cancellation_reason' => $reason,
        'cancelled_at' => now(),
      ]);

      return $payment;
    });
  }

  /**
   * Process refund for a paid payment
   *
   * @param Payment $payment
   * @param float|null $amount Amount to refund (null = full refund)
   * @param string $reason Reason for refund
   * @return Payment
   */
  public function refundPayment(Payment $payment, ?float $amount = null, string $reason = ''): Payment
  {
    return DB::transaction(function () use ($payment, $amount, $reason) {
      try {
        // Check if payment is refundable
        if ($payment->status_id != PaymentStatus::paid()) {
          throw new \Exception('Only paid payments can be refunded');
        }

        // Check if within refund window
        if (!$this->isWithinRefundWindow($payment)) {
          throw new \Exception('Payment is outside refund window');
        }

        $refundAmount = $amount ?? $payment->amount;

        // Update payment with refund info
        $payment->update([
          'refunded_amount' => $refundAmount,
          'refund_reason' => $reason,
          'refunded_at' => now(),
        ]);

        Log::info("Payment refunded for booking #{$payment->booking_id}", [
          'payment_id' => $payment->id,
          'amount' => $refundAmount,
          'reason' => $reason,
        ]);

        return $payment;
      } catch (\Exception $e) {
        Log::error("Failed to refund payment #{$payment->id}: {$e->getMessage()}");
        throw $e;
      }
    });
  }

  /**
   * Mark expired payments
   *
   * @return int Number of payments marked as expired
   */
  public function expireOldPayments(): int
  {
    return Payment::where('status_id', PaymentStatus::pending())
      ->where('expires_at', '<', now())
      ->update(['status_id' => PaymentStatus::expired()]);
  }

  /**
   * Check if payment is within refund window
   *
   * @param Payment $payment
   * @return bool
   */
  private function isWithinRefundWindow(Payment $payment): bool
  {
    $refundDeadline = $payment->verified_at->addHours(PaymentConstants::REFUND_ELIGIBLE_HOURS);
    return now()->lessThanOrEqualTo($refundDeadline);
  }

  /**
   * Get payment summary for a user
   *
   * @param \App\Models\User $user
   * @return array
   */
  public function getUserPaymentSummary($user): array
  {
    $payments = Payment::whereHas('booking', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->get();

    return [
      'total_payments' => $payments->count(),
      'total_amount' => $payments->sum('amount'),
      'paid_amount' => $payments->where('status_id', PaymentStatus::paid())->sum('amount'),
      'pending_amount' => $payments->where('status_id', PaymentStatus::pending())->sum('amount'),
      'failed_amount' => $payments->where('status_id', PaymentStatus::failed())->sum('amount'),
      'refunded_amount' => $payments->sum('refunded_amount'),
    ];
  }
}
