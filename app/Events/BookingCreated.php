<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated
{
    use Dispatchable, SerializesModels;

    /**
     * 🔥 Data utama event
     */
    public Booking $booking;

    /**
     * 🔥 Optional metadata (future-proof)
     */
    public ?array $meta;

    /**
     * Create a new event instance.
     */
    public function __construct(Booking $booking, ?array $meta = null)
    {
        $this->booking = $booking->load([
            'user',
            'court',
            'timeSlots',
            'status'
        ]);
        $this->meta = $meta;
    }
}
