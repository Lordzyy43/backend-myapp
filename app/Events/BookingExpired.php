<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingExpired
{
    use Dispatchable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        // optional: langsung load relasi biar listener siap pakai
        $this->booking = $booking->load(['user', 'court', 'timeSlots', 'status']);
    }
}