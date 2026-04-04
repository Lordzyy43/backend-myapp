<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;
use App\Models\BookingStatus;

class ReviewController extends Controller
{
    /**
     * Store Review
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'review_text' => 'nullable|string'
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $booking = Booking::with(['court'])->findOrFail($request->booking_id);

        // 🔥 VALIDASI CORE (REAL WORLD)
        if ($booking->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // hanya booking yang sudah selesai / confirmed
        if ($booking->status_id !== BookingStatus::confirmed()) {
            return response()->json([
                'message' => 'Review hanya bisa untuk booking yang sudah selesai'
            ], 400);
        }

        // 🔥 CEK DUPLIKAT
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json([
                'message' => 'Review sudah pernah dibuat'
            ], 409);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'venue_id' => $booking->court->venue_id,
            'court_id' => $booking->court_id,
            'rating' => $request->rating,
            'review_text' => $request->review_text,
        ]);

        return response()->json([
            'message' => 'Review berhasil dibuat',
            'data' => $review
        ], 201);
    }

    /**
     * Get reviews by court
     */
    public function getByCourt($courtId)
    {
        $reviews = Review::with('user')
            ->where('court_id', $courtId)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $reviews
        ]);
    }

    /**
     * Get reviews by venue
     */
    public function getByVenue($venueId)
    {
        $reviews = Review::with('user')
            ->where('venue_id', $venueId)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $reviews
        ]);
    }
}
