<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Models\PaymentStatus;

class PaymentPolicy
{
  public function viewAny(User $user): bool
  {
    return true;
  }

  public function view(User $user, Payment $payment): bool
  {
    return $user->isAdmin()
      || $payment->booking?->user_id === $user->id;
  }

  public function create(User $user): bool
  {
    return true;
  }

  public function update(User $user, Payment $payment): bool
  {
    return $payment->booking->user_id === $user->id;
  }

  public function cancel(User $user, Payment $payment): bool
  {
    return $payment->booking->user_id === $user->id;
  }

  public function approve(User $user, Payment $payment): bool
  {
    return $user->isAdmin();
  }

  public function delete(User $user, Payment $payment): bool
  {
    return false;
  }
}
