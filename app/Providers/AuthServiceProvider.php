<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
  protected $policies = [
    Booking::class => BookingPolicy::class,
    Payment::class => PaymentPolicy::class,
    User::class => UserPolicy::class,
  ];

  public function boot(): void
  {
    $this->registerPolicies();
  }
}
