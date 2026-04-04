<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Booking;
use App\Models\Payment;
use App\Policies\BookingPolicy;
use App\Policies\PaymentPolicy;

class AuthServiceProvider extends ServiceProvider
{
  protected $policies = [
    Booking::class => BookingPolicy::class,
    Payment::class => PaymentPolicy::class,
  ];

  public function boot(): void
  {
    $this->registerPolicies();
  }
}
