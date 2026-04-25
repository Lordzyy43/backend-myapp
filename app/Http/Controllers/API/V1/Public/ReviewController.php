<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use App\Http\Resources\V1\Public\ReviewResource;
use Illuminate\Http\Request;
use App\Models\Review;

class ReviewController extends Controller
{
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
      // Eager load 'user' untuk performa N+1
      $reviews = Review::with(['user'])->latest()->paginate($perPage);

      return $this->success(
        ReviewResource::collection($reviews)->response()->getData(true),
        'Reviews retrieved successfully'
      );
    } catch (\Exception $e) {
      return $this->error('Failed to retrieve reviews', $e->getMessage(), 500);
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
