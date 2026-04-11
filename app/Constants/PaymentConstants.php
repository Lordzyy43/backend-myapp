<?php

namespace App\Constants;

/**
 * Payment Status Constants
 * Define all possible payment states
 */
class PaymentConstants
{
  // Payment Status IDs (mapped to PaymentStatus model)
  const STATUS_PENDING = 'pending';           // Payment created, awaiting confirmation
  const STATUS_PAID = 'paid';                 // Payment successfully confirmed
  const STATUS_CANCELLED = 'cancelled';       // Payment cancelled by user
  const STATUS_FAILED = 'failed';             // Payment processing failed
  const STATUS_EXPIRED = 'expired';           // Payment deadline passed

  // Payment Time Limits
  const PAYMENT_EXPIRY_MINUTES = 60;          // Payment expires after 60 min
  const PAYMENT_VERIFICATION_TIMEOUT = 5;    // Max seconds to wait for verification

  // Payment Rules
  const REFUND_ELIGIBLE_HOURS = 24;           // Can refund within 24 hours
  const MAX_RETRY_ATTEMPTS = 3;               // Max payment retry attempts
  const RETRY_DELAY_SECONDS = 10;             // Delay between retries

  /**
   * Get all payment statuses
   */
  public static function getAllStatuses(): array
  {
    return [
      self::STATUS_PENDING,
      self::STATUS_PAID,
      self::STATUS_CANCELLED,
      self::STATUS_FAILED,
      self::STATUS_EXPIRED,
    ];
  }

  /**
   * Get refundable statuses
   */
  public static function getRefundableStatuses(): array
  {
    return [self::STATUS_PAID];
  }
}
