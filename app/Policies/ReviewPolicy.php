<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
  /**
   * Determine if the user can view any reviews
   */
  public function viewAny(User $user): bool
  {
    return true; // Anyone can view reviews
  }

  /**
   * Determine if the user can view the review
   */
  public function view(User $user, Review $review): bool
  {
    return true; // Anyone can view review details
  }

  /**
   * Determine if the user can create a review
   */
  public function create(User $user): bool
  {
    return true; // Authenticated users can create reviews
  }

  /**
   * Determine if the user can update the review
   */
  public function update(User $user, Review $review): bool
  {
    // Only the review author or admin can update
    if ($user->role->role_name === 'admin') {
      return true;
    }

    return $user->id === $review->user_id;
  }

  /**
   * Determine if the user can delete the review
   */
  public function delete(User $user, Review $review): bool
  {
    // Only review author or admin can delete
    if ($user->role->role_name === 'admin') {
      return true;
    }

    return $user->id === $review->user_id;
  }

  /**
   * Determine if the user can control visibility of review
   */
  public function approve(User $user): bool
  {
    // Only admin can approve/moderate reviews
    return $user->role->role_name === 'admin';
  }

  /**
   * Determine if the user can report a review as inappropriate
   */
  public function report(User $user): bool
  {
    return true; // Any authenticated user can report
  }
}
