<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReviewService
 * Handles review operations:
 * - Creating reviews for bookings/courts
 * - Validating review eligibility
 * - Managing review ratings
 * - Calculating average ratings
 */
class ReviewService
{
  /**
   * Create a review for a booking
   *
   * @param Booking $booking
   * @param User $user
   * @param int $rating (1-5)
   * @param string $comment
   * @return Review
   * @throws \Exception
   */
  public function createBookingReview(
    Booking $booking,
    User $user,
    int $rating,
    string $comment = ''
  ): Review {
    return DB::transaction(function () use ($booking, $user, $rating, $comment) {
      // Validate review eligibility
      if (!$this->canReviewBooking($booking, $user)) {
        throw new \Exception('User is not eligible to review this booking');
      }

      // Validate rating
      if ($rating < 1 || $rating > 5) {
        throw new \Exception('Rating must be between 1 and 5');
      }

      // Check if review already exists
      if ($this->hasReviewedBooking($booking, $user)) {
        throw new \Exception('User has already reviewed this booking');
      }

      // Load court to get venue_id
      if (!$booking->court) {
        $booking->load('court');
      }
      if (!$booking->court->venue_id) {
        throw new \Exception('Court venue not found');
      }

      $review = Review::create([
        'booking_id' => $booking->id,
        'court_id' => $booking->court_id,
        'venue_id' => $booking->court->venue_id,
        'user_id' => $user->id,
        'rating' => $rating,
        'review_text' => $comment,
      ]);

      Log::info("Booking review created", [
        'review_id' => $review->id,
        'booking_id' => $booking->id,
        'rating' => $rating,
        'user_id' => $user->id,
      ]);

      // Update court rating
      $this->updateCourtRating($booking->court_id);

      return $review;
    });
  }

  /**
   * Validate if a user can review a booking
   *
   * @param Booking $booking
   * @param User $user
   * @return bool
   */
  public function canReviewBooking(Booking $booking, User $user): bool
  {
    // User must be the one who made the booking
    if ($booking->user_id !== $user->id) {
      return false;
    }

    // Booking must be finished
    if ($booking->status_id !== BookingStatus::finished()) {
      return false;
    }

    return true;
  }

  /**
   * Check if user has already reviewed a booking
   *
   * @param Booking $booking
   * @param User $user
   * @return bool
   */
  public function hasReviewedBooking(Booking $booking, User $user): bool
  {
    return Review::where('booking_id', $booking->id)
      ->where('user_id', $user->id)
      ->exists();
  }

  /**
   * Update a review
   *
   * @param Review $review
   * @param int $rating
   * @param string $comment
   * @return Review
   */
  public function updateReview(
    Review $review,
    int $rating,
    string $comment = ''
  ): Review {
    return DB::transaction(function () use ($review, $rating, $comment) {
      if ($rating < 1 || $rating > 5) {
        throw new \Exception('Rating must be between 1 and 5');
      }

      $oldRating = $review->rating;
      $review->update([
        'rating' => $rating,
        'comment' => $comment,
      ]);

      Log::info("Review updated", [
        'review_id' => $review->id,
        'old_rating' => $oldRating,
        'new_rating' => $rating,
      ]);

      // Update court rating if rating changed
      if ($oldRating !== $rating) {
        $this->updateCourtRating($review->court_id);
      }

      return $review;
    });
  }

  /**
   * Delete a review
   *
   * @param Review $review
   * @return bool
   */
  public function deleteReview(Review $review): bool
  {
    return DB::transaction(function () use ($review) {
      $courtId = $review->court_id;

      if ($review->delete()) {
        Log::info("Review deleted", ['review_id' => $review->id]);

        // Update court rating
        $this->updateCourtRating($courtId);

        return true;
      }

      return false;
    });
  }

  /**
   * Update court's average rating
   *
   * @param int $courtId
   * @return float Average rating
   */
  public function updateCourtRating(int $courtId): float
  {
    try {
      $averageRating = Review::where('court_id', $courtId)
        ->avg('rating');

      $averageRating = $averageRating ?? 0;

      // Update court's rating (assuming court has rating field)
      DB::table('courts')
        ->where('id', $courtId)
        ->update([
          'average_rating' => $averageRating,
          'review_count' => Review::where('court_id', $courtId)->count(),
        ]);

      Log::info("Court rating updated", [
        'court_id' => $courtId,
        'average_rating' => $averageRating,
      ]);

      return $averageRating;
    } catch (\Exception $e) {
      // Log but don't fail if average_rating column doesn't exist
      // This will be implemented in Phase 2
      Log::warning("Court rating update failed - column may not exist", [
        'court_id' => $courtId,
        'error' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Get all reviews for a court
   *
   * @param int $courtId
   * @param int $perPage
   * @return \Illuminate\Pagination\LengthAwarePaginator
   */
  public function getCourtReviews(int $courtId, int $perPage = 10)
  {
    return Review::where('court_id', $courtId)
      ->with('user')
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  /**
   * Get reviews with specific rating
   *
   * @param int $courtId
   * @param int $rating
   * @param int $perPage
   * @return \Illuminate\Pagination\LengthAwarePaginator
   */
  public function getReviewsByRating(int $courtId, int $rating, int $perPage = 10)
  {
    if ($rating < 1 || $rating > 5) {
      throw new \Exception('Rating must be between 1 and 5');
    }

    return Review::where('court_id', $courtId)
      ->where('rating', $rating)
      ->with('user')
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  /**
   * Get rating distribution for a court
   *
   * @param int $courtId
   * @return array
   */
  public function getRatingDistribution(int $courtId): array
  {
    $distribution = [];

    for ($i = 1; $i <= 5; $i++) {
      $count = Review::where('court_id', $courtId)
        ->where('rating', $i)
        ->count();

      $distribution[$i] = $count;
    }

    $total = array_sum($distribution);

    if ($total > 0) {
      foreach ($distribution as &$count) {
        $count = [
          'count' => $count,
          'percentage' => round(($count / $total) * 100, 2),
        ];
      }
    }

    return [
      'distribution' => $distribution,
      'total_reviews' => $total,
      'average_rating' => Review::where('court_id', $courtId)->avg('rating') ?? 0,
    ];
  }

  /**
   * Get user's reviews
   *
   * @param User $user
   * @param int $perPage
   * @return \Illuminate\Pagination\LengthAwarePaginator
   */
  public function getUserReviews(User $user, int $perPage = 10)
  {
    return Review::where('user_id', $user->id)
      ->with('court', 'booking')
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  /**
   * Get statistics for reviews
   *
   * @param int $courtId
   * @return array
   */
  public function getReviewStats(int $courtId): array
  {
    $reviews = Review::where('court_id', $courtId);
    $totalReviews = $reviews->count();

    if ($totalReviews === 0) {
      return [
        'total_reviews' => 0,
        'average_rating' => 0,
        'highest_rated' => 0,
        'lowest_rated' => 0,
        'recent_reviews' => [],
      ];
    }

    return [
      'total_reviews' => $totalReviews,
      'average_rating' => $reviews->avg('rating'),
      'highest_rated' => $reviews->max('rating'),
      'lowest_rated' => $reviews->min('rating'),
      'distribution' => $this->getRatingDistribution($courtId),
    ];
  }

  /**
   * Helpful review (upvote)
   *
   * @param Review $review
   * @return Review
   */
  public function markHelpful(Review $review): Review
  {
    return tap($review)->update([
      'helpful_count' => $review->helpful_count + 1,
    ]);
  }

  /**
   * Report/Flag a review
   *
   * @param Review $review
   * @param string $reason
   * @return bool
   */
  public function reportReview(Review $review, string $reason): bool
  {
    return (bool) $review->update([
      'is_flagged' => true,
      'flag_reason' => $reason,
      'flagged_at' => now(),
    ]);
  }
}
