<?php

namespace App\Constants;

/**
 * Notification Type Constants
 * Define all notification event types in the system
 */
class NotificationConstants
{
  // Booking Notifications
  const BOOKING_CREATED = 'booking_created';           // Booking successfully created
  const BOOKING_APPROVED = 'booking_approved';         // Admin approved booking
  const BOOKING_REJECTED = 'booking_rejected';         // Admin rejected booking
  const BOOKING_EXPIRED = 'booking_expired';           // Booking expired automatically
  const BOOKING_FINISHED = 'booking_finished';         // Booking marked as finished
  const BOOKING_CANCELLED = 'booking_cancelled';       // Booking was cancelled

  // Payment Notifications
  const PAYMENT_CREATED = 'payment_created';           // New payment record created
  const PAYMENT_SUCCESS = 'payment_success';           // Payment successfully confirmed
  const PAYMENT_FAILED = 'payment_failed';             // Payment processing failed
  const PAYMENT_EXPIRED = 'payment_expired';           // Payment deadline passed
  const PAYMENT_CANCELLED = 'payment_cancelled';       // Payment was cancelled

  // User Notifications
  const USER_REGISTERED = 'user_registered';           // New user registered
  const EMAIL_VERIFIED = 'email_verified';             // Email verification completed
  const PASSWORD_RESET = 'password_reset';             // Password reset initiated

  // Platform Notifications
  const PROMO_EXPIRED = 'promo_expired';               // Promo code expired
  const VENUE_CREATED = 'venue_created';               // New venue created
  const REVIEW_POSTED = 'review_posted';               // New review posted

  // System Notifications
  const MAINTENANCE_ALERT = 'maintenance_alert';       // Court maintenance scheduled
  const AVAILABILITY_ALERT = 'availability_alert';     // New availability alert

  /**
   * Get booking-related notification types
   */
  public static function getBookingNotifications(): array
  {
    return [
      self::BOOKING_CREATED,
      self::BOOKING_APPROVED,
      self::BOOKING_REJECTED,
      self::BOOKING_EXPIRED,
      self::BOOKING_FINISHED,
      self::BOOKING_CANCELLED,
    ];
  }

  /**
   * Get payment-related notification types
   */
  public static function getPaymentNotifications(): array
  {
    return [
      self::PAYMENT_CREATED,
      self::PAYMENT_SUCCESS,
      self::PAYMENT_FAILED,
      self::PAYMENT_EXPIRED,
      self::PAYMENT_CANCELLED,
    ];
  }

  /**
   * Check if notification is critical (needs immediate attention)
   */
  public static function isCritical(string $type): bool
  {
    $critical = [
      self::BOOKING_REJECTED,
      self::PAYMENT_FAILED,
      self::BOOKING_EXPIRED,
      self::PAYMENT_EXPIRED,
    ];

    return in_array($type, $critical);
  }
}
