<?php

namespace App\Services;

use App\Constants\PaymentConstants;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
      try {
        // Check if payment already exists
        $existingPayment = Payment::where('booking_id', $booking->id)
          ->whereIn('status_id', [PaymentStatus::pending(), PaymentStatus::paid()])
          ->first();

        if ($existingPayment) {
          throw new \Exception('Payment already exists for this booking');
        }

        // Create new payment record
        $payment = Payment::create([
          'booking_id' => $booking->id,
          'status_id' => PaymentStatus::pending(),
          'amount' => $booking->total_price,
          'payment_method' => $data['payment_method'] ?? 'bank_transfer',
          'transaction_id' => $data['transaction_id'] ?? null,
          'payment_proof' => $data['payment_proof'] ?? null,
          'expires_at' => now()->addMinutes(PaymentConstants::PAYMENT_EXPIRY_MINUTES),
          'verified_at' => null,
        ]);

        Log::info("Payment created for booking #{$booking->id}", ['payment_id' => $payment->id]);

        return $payment;
      } catch (\Exception $e) {
        Log::error("Failed to create payment for booking #{$booking->id}: {$e->getMessage()}");
        throw $e;
      }
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
      try {
        // Check if payment is still valid
        if ($payment->isExpired()) {
          $payment->update(['status_id' => PaymentStatus::expired()]);
          throw new \Exception('Payment has expired');
        }

        if ($payment->status_id != PaymentStatus::pending()) {
          throw new \Exception('Payment has already been processed');
        }

        // Update payment status
        $payment->update([
          'status_id' => PaymentStatus::paid(),
          'verified_at' => now(),
          'verification_data' => $verificationData,
        ]);

        // Update booking status to confirmed
        $payment->booking->update([
          'status_id' => BookingStatus::confirmed(),
        ]);

        Log::info("Payment confirmed for booking #{$payment->booking_id}", ['payment_id' => $payment->id]);

        return $payment;
      } catch (\Exception $e) {
        Log::error("Failed to confirm payment #{$payment->id}: {$e->getMessage()}");
        throw $e;
      }
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
      if ($payment->isCancelled() || $payment->isRefunded()) {
        throw new \Exception('Payment cannot be cancelled in current state');
      }

      $payment->update([
        'status_id' => PaymentStatus::cancelled(),
        'cancellation_reason' => $reason,
        'cancelled_at' => now(),
      ]);

      Log::info("Payment cancelled for booking #{$payment->booking_id}", [
        'payment_id' => $payment->id,
        'reason' => $reason,
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
