<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Booking;

class BookingPolicy
{
    /**
     * Helper untuk cek apakah user adalah admin
     */
    private function isAdmin(User $user): bool
    {
        // Gunakan optional() atau null safe operator jika role bisa null
        return $user->role?->role_name === 'admin';
    }

    /**
     * Helper untuk cek apakah user adalah pemilik booking
     */
    private function isOwner(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    /**
     * VIEW ANY (Laporan/List Admin)
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * VIEW DETAIL
     */
    public function view(User $user, Booking $booking): bool
    {
        return $this->isAdmin($user) || $this->isOwner($user, $booking);
    }

    /**
     * CANCEL (User & Admin)
     * Kita bebaskan aksesnya di sini, validasi status id ada di Service.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $this->isAdmin($user) || $this->isOwner($user, $booking);
    }

    /**
     * APPROVE (Admin Only)
     */
    public function approve(User $user, Booking $booking): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * REJECT (Admin Only)
     */
    public function reject(User $user, Booking $booking): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * FINISH (Admin Only)
     */
    public function finish(User $user, Booking $booking): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * PAY (Owner Only)
     */
    public function pay(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking);
    }
}
