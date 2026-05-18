<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
  /**
   * 🔥 GLOBAL OVERRIDE (HIGH PRIORITY)
   * - Admin selalu boleh (production)
   * - Non-admin tetap mengikuti policy masing-masing action
   */
  public function before(User $user, string $ability): bool|null
  {
    if ($user->isAdmin()) {
      return true;
    }

    return null;
  }

  /**
   * View any payments
   */
  public function viewAny(User $user): bool
  {
    return true;
  }

  /**
   * View specific payment
   */
  public function view(User $user, Payment $payment): bool
  {
    return $payment->booking?->user_id === $user->id;
  }

  /**
   * Create payment
   */
  public function create(User $user): bool
  {
    return true;
  }

  /**
   * Update payment (user confirm / edit)
   */
  public function update(User $user, Payment $payment): bool
  {
    return $payment->booking?->user_id === $user->id;
  }

  /**
   * Cancel payment
   */
  public function cancel(User $user, Payment $payment): bool
  {
    return $payment->booking?->user_id === $user->id;
  }

  /**
   * Approve payment (admin only, handled by before())
   */
  public function approve(User $user, Payment $payment): bool
  {
    // 🔥 Tidak perlu logic lagi karena:
    // - admin sudah di-handle di before()
    // - non-admin akan otomatis false
    return false;
  }

  /**
   * Delete payment (disabled)
   */
  public function delete(User $user, Payment $payment): bool
  {
    return false;
  }
}
