<?php

namespace App\Constants;

/**
 * Booking Status Constants
 * Define all possible states throughout the booking lifecycle
 */
class BookingConstants
{
  // Booking Status IDs (mapped to BookingStatus model)
  const STATUS_PENDING = 'pending';           // User created booking, awaiting approval/payment
  const STATUS_CONFIRMED = 'confirmed';       // Booking approved and payment confirmed
  const STATUS_CANCELLED = 'cancelled';       // Booking cancelled by user or admin
  const STATUS_EXPIRED = 'expired';           // Booking expired due to time limit
  const STATUS_FINISHED = 'finished';         // Booking completed/check-in done

  // Booking Time Limits (in minutes)
  const BOOKING_EXPIRY_MINUTES = 30;          // Pending booking expires after 30 min
  const PAYMENT_REMINDER_MINUTES = 15;        // Send payment reminder at 15 min

  // Booking Rules
  const MIN_ADVANCE_BOOKING_HOURS = 1;        // Can only book at least 1 hour in advance
  const MAX_ADVANCE_BOOKING_DAYS = 90;        // Can only book max 90 days in advance
  const BOOKING_CANCELLATION_HOURS = 24;      // Must cancel at least 24 hours before

  /**
   * Get all status values
   */
  public static function getAllStatuses(): array
  {
    return [
      self::STATUS_PENDING,
      self::STATUS_CONFIRMED,
      self::STATUS_CANCELLED,
      self::STATUS_EXPIRED,
      self::STATUS_FINISHED,
    ];
  }

  /**
   * Get cancellable statuses
   */
  public static function getCancellableStatuses(): array
  {
    return [self::STATUS_PENDING, self::STATUS_CONFIRMED];
  }

  /**
   * Check if status allows cancellation
   */
  public static function canCancel(string $status): bool
  {
    return in_array($status, self::getCancellableStatuses());
  }
}
