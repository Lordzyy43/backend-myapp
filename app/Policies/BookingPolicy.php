<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Booking;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    /**
     * 🔥 SUPERPOWERS (Admin Bypass)
     * Laravel akan menjalankan ini sebelum method lain.
     * Jika return true, admin bisa melakukan apa saja tanpa perlu cek method di bawah.
     */
    public function before(User $user, $ability)
    {
        if ($user->role?->role_name === 'admin') {
            return true;
        }
    }

    /**
     * Helper Internal: Cek Kepemilikan
     */
    private function isOwner(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    /**
     * VIEW ANY: Hanya admin yang bisa melihat list semua booking global.
     * (User biasa melihat list lewat endpoint 'me/bookings' yang sudah difilter di Controller)
     */
    public function viewAny(User $user): bool
    {
        return false; // Diblokir karena 'before' sudah menghandle admin
    }

    /**
     * VIEW DETAIL: Hanya pemilik atau admin.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking)
            ? Response::allow()
            : Response::deny('Kamu tidak memiliki akses ke detail booking ini.');
    }

    /**
     * CREATE: Semua user yang sudah login/verified boleh buat booking.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * CANCEL: Hanya pemilik yang bisa cancel booking-nya sendiri.
     * Note: Logika status (apakah sudah lewat jam dsb) tetap di Service/Controller.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking);
    }

    /**
     * PAY: Sangat krusial! Hanya pemilik yang boleh membayar.
     * Jangan biarkan orang lain membayar booking orang lain tanpa izin.
     */
    public function pay(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking);
    }

    /**
     * MANAGE (Approve, Reject, Finish): Khusus Admin.
     * Method di bawah ini akan otomatis return false untuk user biasa,
     * dan true untuk admin (karena ditangani oleh method 'before').
     */
    public function approve(User $user, Booking $booking): bool
    {
        return false;
    }
    public function reject(User $user, Booking $booking): bool
    {
        return false;
    }
    public function finish(User $user, Booking $booking): bool
    {
        return false;
    }
}
