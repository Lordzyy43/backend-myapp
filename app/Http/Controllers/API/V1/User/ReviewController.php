<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;
use App\Http\Resources\V1\User\ReviewResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ReviewController extends Controller
{
    use AuthorizesRequests;

    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * CREATE REVIEW - Area User
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000'
            ]);

            $user = auth()->user();
            $booking = Booking::with(['court'])->findOrFail($request->booking_id);

            // 🔥 Validasi kepemilikan booking
            if ($booking->user_id !== $user->id) {
                return $this->forbidden('Ini bukan bookingan kamu, Yogi!');
            }

            // 🔥 Cek apakah sudah bisa direview (Finished)
            if (!$this->reviewService->canReviewBooking($booking, $user)) {
                return $this->error(
                    'Belum bisa kasih review',
                    ['message' => 'Hanya booking yang sudah selesai yang bisa diulas'],
                    400
                );
            }

            // Sesuai parameter service kamu: booking, user, rating, comment
            $review = $this->reviewService->createBookingReview(
                $booking,
                $user,
                $request->rating,
                $request->comment ?? ''
            );

            return $this->created(
                new ReviewResource($review->load(['user', 'court'])),
                'Review berhasil dibuat'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal membuat review', $e->getMessage(), 400);
        }
    }

    /**
     * UPDATE REVIEW - Area User
     */
    public function update(Request $request, string $id)
    {
        try {
            $review = Review::findOrFail($id);

            // Cek Authorize (Policy atau Manual)
            if ($review->user_id !== auth()->id()) {
                return $this->forbidden('Kamu tidak berhak mengubah review ini');
            }

            $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'sometimes|string|max:1000'
            ]);

            // Sesuai parameter service: review, rating, comment
            $updatedReview = $this->reviewService->updateReview(
                $review,
                $request->get('rating', $review->rating),
                $request->get('comment', $review->review_text) // Pastikan fieldnya review_text atau comment sesuai DB
            );

            return $this->success(
                new ReviewResource($updatedReview),
                'Review berhasil diupdate'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal update review', $e->getMessage(), 400);
        }
    }

    /**
     * DELETE REVIEW
     */
    public function destroy(string $id)
    {
        try {
            $review = Review::findOrFail($id);

            if ($review->user_id !== auth()->id()) {
                return $this->forbidden();
            }

            $this->reviewService->deleteReview($review);

            return $this->success(null, 'Review berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus review', $e->getMessage(), 400);
        }
    }

    /**
     * GET USER'S OWN REVIEWS
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->get('per_page', 10);

            $reviews = $this->reviewService->getUserReviews($user, $perPage);

            return $this->success(
                ReviewResource::collection($reviews)->response()->getData(true),
                'Daftar review kamu berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data review', $e->getMessage(), 500);
        }
    }

    /**
     * ACTION: MARK AS HELPFUL & REPORT
     */
    public function markHelpful(string $id)
    {
        $review = Review::findOrFail($id);
        $result = $this->reviewService->markHelpful($review);
        return $this->success($result, 'Berhasil menandai ulasan sebagai bermanfaat');
    }

    public function report(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $review = Review::findOrFail($id);
        $result = $this->reviewService->reportReview($review, $request->reason);
        return $this->success($result, 'Ulasan berhasil dilaporkan');
    }
}
