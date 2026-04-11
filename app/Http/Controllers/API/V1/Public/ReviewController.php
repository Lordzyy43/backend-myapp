<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;
use App\Models\BookingStatus;
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
     * LIST ALL REVIEWS (PUBLIC)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $reviews = Review::with(['user', 'court'])
                ->latest()
                ->paginate($perPage);

            return $this->success([
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ], 'Reviews retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve reviews', $e->getMessage(), 500);
        }
    }

    /**
     * CREATE REVIEW - Using ReviewService
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

            if (!$user) {
                return $this->unauthorized();
            }

            $booking = Booking::with(['court'])->findOrFail($request->booking_id);

            // 🔥 Authorize access
            if ($booking->user_id !== $user->id) {
                return $this->forbidden();
            }

            // Check if booking is finished (using service validation)
            if (!$this->reviewService->canReviewBooking($booking, $user)) {
                return $this->error(
                    'Cannot create review for this booking',
                    ['message' => 'Only finished bookings can be reviewed'],
                    400
                );
            }

            // Create review using service
            $review = $this->reviewService->createBookingReview(
                $booking,
                $user,
                $request->rating,
                $request->comment ?? ''
            );

            return $this->created([
                'review' => $review->load(['user', 'court']),
            ], 'Review created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create review', $e->getMessage(), 400);
        }
    }

    /**
     * SHOW SINGLE REVIEW
     */
    public function show(string $id)
    {
        try {
            $review = Review::with(['user', 'court', 'booking'])->findOrFail($id);

            return $this->success($review, 'Review retrieved successfully');
        } catch (\Exception $e) {
            return $this->notFound('Review not found');
        }
    }

    /**
     * UPDATE REVIEW - Using ReviewService
     */
    public function update(Request $request, string $id)
    {
        try {
            $review = Review::findOrFail($id);

            $this->authorize('update', $review);

            $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'sometimes|string|max:1000'
            ]);

            $user = auth()->user();

            // Only author or admin can update
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->forbidden();
            }

            $review = $this->reviewService->updateReview(
                $review,
                $request->get('rating', $review->rating),
                $request->get('comment', $review->comment)
            );

            return $this->success($review, 'Review updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update review', $e->getMessage(), 400);
        }
    }

    /**
     * DELETE REVIEW - Using ReviewService
     */
    public function destroy(string $id)
    {
        try {
            $review = Review::findOrFail($id);

            $this->authorize('delete', $review);

            $user = auth()->user();

            // Only author or admin can delete
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                return $this->forbidden();
            }

            $this->reviewService->deleteReview($review);

            return $this->success(null, 'Review deleted successfully');
        } catch (\Exception $e) {
            return $this->notFound('Review not found');
        }
    }

    /**
     * GET REVIEWS BY COURT - With pagination
     */
    public function getByCourt(Request $request, $courtId)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $reviews = $this->reviewService->getCourtReviews($courtId, $perPage);

            return $this->success([
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ], 'Court reviews retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve court reviews', $e->getMessage(), 500);
        }
    }

    /**
     * GET REVIEWS BY VENUE
     */
    public function getByVenue(Request $request, $venueId)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $reviews = Review::with('user')
                ->where('venue_id', $venueId)
                ->latest()
                ->paginate($perPage);

            return $this->success([
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ], 'Venue reviews retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve venue reviews', $e->getMessage(), 500);
        }
    }

    /**
     * GET RATING DISTRIBUTION FOR COURT
     */
    public function getRatingDistribution($courtId)
    {
        try {
            $distribution = $this->reviewService->getRatingDistribution($courtId);

            return $this->success($distribution, 'Rating distribution retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve rating distribution', $e->getMessage(), 500);
        }
    }

    /**
     * GET REVIEWS FILTERED BY RATING
     */
    public function getByRating(Request $request, $courtId)
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $perPage = $request->get('per_page', 10);

            $reviews = $this->reviewService->getReviewsByRating(
                $courtId,
                $request->rating,
                $perPage
            );

            return $this->success([
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ], 'Reviews retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve reviews', $e->getMessage(), 500);
        }
    }

    /**
     * GET REVIEW STATISTICS FOR COURT
     */
    public function getStatistics($courtId)
    {
        try {
            $stats = $this->reviewService->getReviewStats($courtId);

            return $this->success($stats, 'Review statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics', $e->getMessage(), 500);
        }
    }

    /**
     * MARK REVIEW AS HELPFUL
     */
    public function markHelpful(string $id)
    {
        try {
            $review = Review::findOrFail($id);

            $updated = $this->reviewService->markHelpful($review);

            return $this->success($updated, 'Review marked as helpful');
        } catch (\Exception $e) {
            return $this->notFound('Review not found');
        }
    }

    /**
     * REPORT REVIEW
     */
    public function report(Request $request, string $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:255',
            ]);

            $review = Review::findOrFail($id);

            $updated = $this->reviewService->reportReview($review, $request->reason);

            return $this->success(
                $updated,
                'Review reported successfully. Our team will review it shortly.'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to report review', $e->getMessage(), 400);
        }
    }

    /**
     * GET USER'S REVIEWS
     */
    public function getUserReviews(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->get('per_page', 10);

            $reviews = $this->reviewService->getUserReviews($user, $perPage);

            return $this->success([
                'reviews' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ], 'Your reviews retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve your reviews', $e->getMessage(), 500);
        }
    }
}
