<?php

namespace App\Policies;

use App\Models\Venue;
use App\Models\User;

class VenuePolicy
{
  /**
   * Determine if the user can view any venues
   */
  public function viewAny(User $user): bool
  {
    return true; // Anyone can view venue list
  }

  /**
   * Determine if the user can view the venue
   */
  public function view(User $user, Venue $venue): bool
  {
    return true; // Anyone can view venue details
  }

  /**
   * Determine if the user can create venues
   */
  public function create(User $user): bool
  {
    // Admin or special "owner" role can create venues
    return in_array($user->role->role_name, ['admin', 'owner']);
  }

  /**
   * Determine if the user can update the venue
   */
  public function update(User $user, Venue $venue): bool
  {
    // Admin can always update
    if ($user->role->role_name === 'admin') {
      return true;
    }

    // Venue owner can only update their own venue
    return $user->id === $venue->owner_id;
  }

  /**
   * Determine if the user can delete the venue
   */
  public function delete(User $user, Venue $venue): bool
  {
    // Only admin or venue owner can delete
    if ($user->role->role_name === 'admin') {
      return true;
    }

    return $user->id === $venue->owner_id;
  }

  /**
   * Determine if the user can restore a deleted venue
   */
  public function restore(User $user, Venue $venue): bool
  {
    return $user->role->role_name === 'admin';
  }

  /**
   * Determine if the user can force delete a venue
   */
  public function forceDelete(User $user, Venue $venue): bool
  {
    return $user->role->role_name === 'admin';
  }

  /**
   * Determine if the user can manage venue images
   */
  public function manageImages(User $user, Venue $venue): bool
  {
    return $this->update($user, $venue);
  }

  /**
   * Determine if the user can set operating hours
   */
  public function setOperatingHours(User $user, Venue $venue): bool
  {
    return $this->update($user, $venue);
  }
}
