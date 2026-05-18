<?php

namespace App\Http\Requests\API\V1\User;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Authorize: Hanya user yang memiliki booking tersebut yang bisa kasih review.
     */
    public function authorize(): bool
    {
        $booking = Booking::find($this->booking_id);

        // 1. Booking harus ada
        // 2. Booking harus milik user yang login
        // 3. Pastikan user belum pernah kasih review untuk booking ini (biar nggak spam)
        $alreadyReviewed = Review::where('booking_id', $this->booking_id)->exists();

        return $booking && $booking->user_id == auth()->id() && !$alreadyReviewed;
    }

    /**
     * Rules: Validasi input rating dan teks.
     */
    public function rules(): array
    {
        return [
            'booking_id'  => 'required|exists:bookings,id',
            'rating'      => 'required|integer|min:1|max:5', // Skala 1-5 bintang
            'review_text' => 'nullable|string|min:5|max:500',
        ];
    }

    /**
     * Custom Messages
     */
    public function messages(): array
    {
        return [
            'booking_id.required' => 'Booking ID wajib disertakan.',
            'rating.required'     => 'Kasih rating bintangnya dulu dong.',
            'rating.min'          => 'Minimal rating adalah 1 bintang.',
            'rating.max'          => 'Maksimal rating cuma sampai 5 bintang ya.',
            'review_text.min'     => 'Review kepanjangan? Eh, maksudnya minimal 5 karakter biar jelas.',
            'review_text.max'     => 'Review jangan kepanjangan, maksimal 500 karakter saja.',
        ];
    }
}
