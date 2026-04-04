<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingApproved
{
    use Dispatchable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        // 🔥 optional: load relasi biar siap dipakai di listener
        $this->booking = $booking->load(['user', 'court', 'timeSlots', 'status']);
    }
}
