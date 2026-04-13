<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// 🔥 BOOKING EVENTS
use App\Events\BookingCreated;
use App\Events\BookingApproved;
use App\Events\BookingRejected;
use App\Events\BookingCancelled;
use App\Events\BookingFinished;
use App\Events\BookingExpired;

// 🔥 PAYMENT EVENTS
use App\Events\PaymentCreated;
use App\Events\PaymentSuccess;
use App\Events\PaymentCancelled;
use App\Events\PaymentExpired;

// 🔥 LISTENERS
use App\Listeners\SendBookingCreatedNotification;
use App\Listeners\SendBookingApprovedNotification;
use App\Listeners\SendBookingRejectedNotification;
use App\Listeners\SendBookingCancelledNotification;
use App\Listeners\SendBookingFinishedNotification;
use App\Listeners\SendBookingExpiredNotification; // Optional, bisa re-use SendBookingFinishedNotification

use App\Listeners\SendPaymentCreatedNotification;
use App\Listeners\SendPaymentSuccessNotification;
use App\Listeners\SendPaymentCancelledNotification;
use App\Listeners\SendPaymentExpiredNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 🔥 Mapping event → listener
     */
    protected $listen = [

        // ======================
        // BOOKING
        // ======================

        BookingCreated::class => [
            SendBookingCreatedNotification::class,
        ],

        BookingApproved::class => [
            SendBookingApprovedNotification::class,
        ],

        BookingRejected::class => [
            SendBookingRejectedNotification::class,
        ],
        BookingCancelled::class => [
            SendBookingCancelledNotification::class,
        ],
        BookingFinished::class => [
            SendBookingFinishedNotification::class,
        ],

        BookingExpired::class => [
            // Optional: bisa buat listener khusus untuk expired, atau re-use SendBookingFinishedNotification
            SendBookingExpiredNotification::class,
        ],

        // ======================
        // PAYMENT
        // ======================
        PaymentCreated::class => [
            SendPaymentCreatedNotification::class,
        ],

        PaymentSuccess::class => [
            SendPaymentSuccessNotification::class,
        ],

        PaymentCancelled::class => [
            SendPaymentCancelledNotification::class,
        ],
        PaymentExpired::class => [
            SendPaymentExpiredNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
