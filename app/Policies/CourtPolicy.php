<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

class CourtPolicy
{
  /**
   * Determine if the user can view any courts
   */
  public function viewAny(User $user): bool
  {
    return true; // Anyone can view court list
  }

  /**
   * Determine if the user can view the court
   */
  public function view(User $user, Court $court): bool
  {
    return true; // Anyone can view court details
  }

  /**
   * Determine if the user can create courts
   */
  public function create(User $user): bool
  {
    return $user->role->role_name === 'admin'; // Only admin can create courts
  }

  /**
   * Determine if the user can update the court
   */
  public function update(User $user, Court $court): bool
  {
    // Admin or venue owner can update court
    if ($user->role->role_name === 'admin') {
      return true;
    }

    // Venue owner can update court in their venue
    if ($user->id === $court->venue->owner_id) {
      return true;
    }

    return false;
  }

  /**
   * Determine if the user can delete the court
   */
  public function delete(User $user, Court $court): bool
  {
    // Only admin can delete courts (soft delete)
    return $user->role->role_name === 'admin';
  }

  /**
   * Determine if the user can toggle court availability
   */
  public function toggleAvailability(User $user, Court $court): bool
  {
    return $this->update($user, $court);
  }

  /**
   * Determine if the user can set pricing
   */
  public function setPricing(User $user, Court $court): bool
  {
    return $this->update($user, $court);
  }
}
