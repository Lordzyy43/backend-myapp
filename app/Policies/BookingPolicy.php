<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Booking;
use App\Models\BookingStatus;

class BookingPolicy
{
    /**
     * 🔥 VIEW BOOKING
     */
    public function view(User $user, Booking $booking): bool
    {
        // user boleh lihat miliknya
        if ($booking->user_id === $user->id) {
            return true;
        }

        // admin boleh lihat semua
        return $user->role->role_name === 'admin';
    }

    /**
     * 🔥 CANCEL (USER)
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            && in_array($booking->status_id, [
                BookingStatus::pending(),
                BookingStatus::confirmed()
            ]);
    }

    /**
     * 🔥 APPROVE (ADMIN)
     */
    public function approve(User $user, Booking $booking): bool
    {
        return $user->role->role_name === 'admin'
            && $booking->status_id === BookingStatus::pending();
    }

    /**
     * 🔥 REJECT (ADMIN)
     */
    public function reject(User $user, Booking $booking): bool
    {
        return $user->role->role_name === 'admin'
            && $booking->status_id === BookingStatus::pending();
    }

    /**
     * 🔥 FINISH (ADMIN)
     */
    public function finish(User $user, Booking $booking): bool
    {
        return $user->role->role_name === 'admin'
            && $booking->status_id === BookingStatus::confirmed();
    }

    /**
     * 🔥 PAY (USER)
     */
    public function pay(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id
            && $booking->status_id === BookingStatus::pending();
    }
}
