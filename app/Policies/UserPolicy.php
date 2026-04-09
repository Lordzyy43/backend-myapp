<?php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Izinkan Admin untuk melihat daftar semua user.
     */
    public function viewAny(User $user): bool
    {
        // Pastikan method isAdmin() sudah ada di Model User kamu ya
        return $user->role->role_name === 'admin';
    }

    /**
     * Izinkan Admin untuk melihat detail user tertentu.
     */
    public function view(User $user, User $model): bool
    {
        return $user->role->role_name === 'admin' || $user->id === $model->id;
    }
}