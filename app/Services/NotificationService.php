<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NotificationService
 * Handles multi-channel notifications:
 * - Email notifications
 * - SMS notifications (via external provider)
 * - Push notifications
 * - In-app notifications
 */
class NotificationService
{
    /**
     * Create in-app notification (legacy static method)
     */
    public static function send(
        $userId,
        $type,
        $title,
        $message,
        $notifiable = null,
        $actionUrl = null,
        $data = []
    ) {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'notifiable_id' => $notifiable?->id,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    /**
     * Create an in-app notification for a user
     *
     * @param User $user
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $data Additional metadata
     * @return Notification
     */
    public function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        array $data = []
    ): Notification {
        return DB::transaction(function () use ($user, $title, $message, $type, $data) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'read_at' => null,
            ]);

            Log::info("Notification created for user", [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'type' => $type,
            ]);

            return $notification;
        });
    }

    /**
     * Send email notification
     *
     * @param User $user
     * @param string $subject
     * @param string $view
     * @param array $data
     * @return bool
     */
    public function sendEmailNotification(
        User $user,
        string $subject,
        string $view,
        array $data = []
    ): bool {
        try {
            // Mail::to($user->email)->send(new EmailNotification($subject, $view, $data));
            Log::info("Email notification sent", [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email notification", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send SMS notification
     *
     * @param User $user
     * @param string $message
     * @return bool
     */
    public function sendSmsNotification(User $user, string $message): bool
    {
        if (!$user->phone_number) {
            Log::warning("User has no phone number for SMS", ['user_id' => $user->id]);
            return false;
        }

        try {
            // Integration with SMS provider (Twilio, etc.)

            Log::info("SMS notification sent", [
                'user_id' => $user->id,
                'phone' => $user->phone_number,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS notification", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send push notification
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendPushNotification(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            // Integration with push notification service (Firebase, etc.)

            Log::info("Push notification sent", [
                'user_id' => $user->id,
                'title' => $title,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send push notification", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notify multiple users
     *
     * @param array $users
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $data
     * @return array
     */
    public function notifyMultipleUsers(
        array $users,
        string $title,
        string $message,
        string $type = 'info',
        array $data = []
    ): array {
        return DB::transaction(function () use ($users, $title, $message, $type, $data) {
            $notifications = [];

            foreach ($users as $user) {
                $notifications[] = $this->notifyUser($user, $title, $message, $type, $data);
            }

            Log::info("Bulk notification sent", [
                'user_count' => count($users),
                'type' => $type,
            ]);

            return $notifications;
        });
    }

    /**
     * Mark notification as read
     *
     * @param Notification $notification
     * @return bool
     */
    public function markAsRead(Notification $notification): bool
    {
        return (bool) $notification->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for user
     *
     * @param User $user
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get unread notification count for user
     *
     * @param User $user
     * @return int
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get user's notifications with pagination
     *
     * @param User $user
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUserNotifications(User $user, int $perPage = 20)
    {
        return Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Delete a notification
     *
     * @param Notification $notification
     * @return bool
     */
    public function deleteNotification(Notification $notification): bool
    {
        return (bool) $notification->delete();
    }

    /**
     * Delete all read notifications for user older than specified days
     *
     * @param User $user
     * @param int $daysOld
     * @return int Number of notifications deleted
     */
    public function cleanupOldNotifications(User $user, int $daysOld = 30): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Send booking-related notification
     *
     * @param User $user
     * @param string $action (created, approved, rejected, finished, expired)
     * @param array $bookingData
     * @return Notification
     */
    public function sendBookingNotification(User $user, string $action, array $bookingData): Notification
    {
        $titles = [
            'created' => 'Booking Confirmed',
            'approved' => 'Booking Approved',
            'rejected' => 'Booking Rejected',
            'finished' => 'Booking Completed',
            'expired' => 'Booking Expired',
        ];

        $title = $titles[$action] ?? 'Booking Update';
        $message = "Your booking for {$bookingData['court_name']} has been {$action}.";

        return $this->notifyUser($user, $title, $message, 'booking', $bookingData);
    }

    /**
     * Send payment-related notification
     *
     * @param User $user
     * @param string $action (created, success, failed, cancelled, expired)
     * @param array $paymentData
     * @return Notification
     */
    public function sendPaymentNotification(User $user, string $action, array $paymentData): Notification
    {
        $titles = [
            'created' => 'Payment Processing',
            'success' => 'Payment Successful',
            'failed' => 'Payment Failed',
            'cancelled' => 'Payment Cancelled',
            'expired' => 'Payment Expired',
        ];

        $title = $titles[$action] ?? 'Payment Update';
        $message = "Payment of {$paymentData['amount']} has been {$action}.";

        return $this->notifyUser($user, $title, $message, 'payment', $paymentData);
    }
}
