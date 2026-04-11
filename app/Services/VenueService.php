<?php

namespace App\Services;

use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\VenueOperatingHour;
use App\Models\CourtMaintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * VenueService
 * Handles venue operations:
 * - Creating and updating venues
 * - Managing venue images
 * - Operating hours management
 * - Maintenance scheduling
 */
class VenueService
{
  /**
   * Create a new venue
   *
   * @param array $data
   * @return Venue
   */
  public function createVenue(array $data): Venue
  {
    return DB::transaction(function () use ($data) {
      $venue = Venue::create([
        'user_id' => $data['user_id'],
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'address' => $data['address'],
        'city' => $data['city'],
        'state' => $data['state'] ?? '',
        'postal_code' => $data['postal_code'] ?? '',
        'latitude' => $data['latitude'] ?? null,
        'longitude' => $data['longitude'] ?? null,
        'phone_number' => $data['phone_number'] ?? '',
        'email' => $data['email'] ?? '',
        'is_active' => $data['is_active'] ?? true,
      ]);

      Log::info("Venue created", [
        'venue_id' => $venue->id,
        'user_id' => $data['user_id'],
        'name' => $venue->name,
      ]);

      return $venue;
    });
  }

  /**
   * Update a venue
   *
   * @param Venue $venue
   * @param array $data
   * @return Venue
   */
  public function updateVenue(Venue $venue, array $data): Venue
  {
    return DB::transaction(function () use ($venue, $data) {
      $venue->update(array_filter([
        'name' => $data['name'] ?? null,
        'description' => $data['description'] ?? null,
        'address' => $data['address'] ?? null,
        'city' => $data['city'] ?? null,
        'state' => $data['state'] ?? null,
        'postal_code' => $data['postal_code'] ?? null,
        'latitude' => $data['latitude'] ?? null,
        'longitude' => $data['longitude'] ?? null,
        'phone_number' => $data['phone_number'] ?? null,
        'email' => $data['email'] ?? null,
        'is_active' => isset($data['is_active']) ? $data['is_active'] : null,
      ], fn($v) => $v !== null));

      Log::info("Venue updated", ['venue_id' => $venue->id]);

      return $venue;
    });
  }

  /**
   * Delete a venue
   *
   * @param Venue $venue
   * @return bool
   */
  public function deleteVenue(Venue $venue): bool
  {
    return DB::transaction(function () use ($venue) {
      // Delete images
      foreach ($venue->images as $image) {
        $this->deleteVenueImage($image);
      }

      // Delete operating hours
      VenueOperatingHour::where('venue_id', $venue->id)->delete();

      // Log activity
      Log::info("Venue deleted", ['venue_id' => $venue->id]);

      return $venue->delete();
    });
  }

  /**
   * Upload venue image
   *
   * @param Venue $venue
   * @param \Illuminate\Http\UploadedFile $file
   * @param bool $isPrimary
   * @return VenueImage
   */
  public function uploadVenueImage(Venue $venue, $file, bool $isPrimary = false): VenueImage
  {
    return DB::transaction(function () use ($venue, $file, $isPrimary) {
      // Store file
      $path = $file->store("venues/{$venue->id}", 'public');

      // If this is primary, unset previous primary
      if ($isPrimary) {
        VenueImage::where('venue_id', $venue->id)
          ->where('is_primary', true)
          ->update(['is_primary' => false]);
      }

      $image = VenueImage::create([
        'venue_id' => $venue->id,
        'image_path' => $path,
        'is_primary' => $isPrimary || $venue->images()->count() === 0,
      ]);

      Log::info("Venue image uploaded", [
        'venue_id' => $venue->id,
        'image_id' => $image->id,
        'path' => $path,
      ]);

      return $image;
    });
  }

  /**
   * Delete venue image
   *
   * @param VenueImage $image
   * @return bool
   */
  public function deleteVenueImage(VenueImage $image): bool
  {
    return DB::transaction(function () use ($image) {
      // Delete file from storage
      if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
        Storage::disk('public')->delete($image->image_path);
      }

      // If was primary, make another image primary
      if ($image->is_primary) {
        $nextImage = VenueImage::where('venue_id', $image->venue_id)
          ->where('id', '!=', $image->id)
          ->first();

        if ($nextImage) {
          $nextImage->update(['is_primary' => true]);
        }
      }

      Log::info("Venue image deleted", ['image_id' => $image->id]);

      return $image->delete();
    });
  }

  /**
   * Set operating hours for a venue
   *
   * @param Venue $venue
   * @param array $operatingHours Format: ['monday' => ['open' => '08:00', 'close' => '22:00'], ...]
   * @return array
   */
  public function setOperatingHours(Venue $venue, array $operatingHours): array
  {
    return DB::transaction(function () use ($venue, $operatingHours) {
      $daysMap = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 0,
      ];

      // Delete existing hours
      VenueOperatingHour::where('venue_id', $venue->id)->delete();

      $createdHours = [];

      foreach ($operatingHours as $day => $hours) {
        $dayNumber = $daysMap[$day] ?? null;
        if (!$dayNumber) {
          continue;
        }

        $operatingHour = VenueOperatingHour::create([
          'venue_id' => $venue->id,
          'day_of_week' => $dayNumber,
          'opening_time' => $hours['open'] ?? null,
          'closing_time' => $hours['close'] ?? null,
          'is_closed' => $hours['is_closed'] ?? false,
        ]);

        $createdHours[] = $operatingHour;
      }

      Log::info("Venue operating hours set", [
        'venue_id' => $venue->id,
        'hours_count' => count($createdHours),
      ]);

      return $createdHours;
    });
  }

  /**
   * Get operating hours for a venue
   *
   * @param Venue $venue
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getOperatingHours(Venue $venue)
  {
    return VenueOperatingHour::where('venue_id', $venue->id)
      ->orderBy('day_of_week')
      ->get();
  }

  /**
   * Check if venue is open on a specific day
   *
   * @param Venue $venue
   * @param int $dayOfWeek (0-6)
   * @return bool
   */
  public function isOpenOnDay(Venue $venue, int $dayOfWeek): bool
  {
    $operatingHour = VenueOperatingHour::where('venue_id', $venue->id)
      ->where('day_of_week', $dayOfWeek)
      ->first();

    if (!$operatingHour) {
      return false;
    }

    return !$operatingHour->is_closed;
  }

  /**
   * Schedule maintenance for a court in venue
   *
   * @param int $courtId
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   * @param string $reason
   * @return CourtMaintenance
   */
  public function scheduleMaintenance(
    int $courtId,
    \DateTime $startDate,
    \DateTime $endDate,
    string $reason = ''
  ): CourtMaintenance {
    return DB::transaction(function () use ($courtId, $startDate, $endDate, $reason) {
      $maintenance = CourtMaintenance::create([
        'court_id' => $courtId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'reason' => $reason,
        'status' => 'scheduled',
      ]);

      Log::info("Court maintenance scheduled", [
        'court_id' => $courtId,
        'maintenance_id' => $maintenance->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
      ]);

      return $maintenance;
    });
  }

  /**
   * Get active maintenance periods
   *
   * @param int $courtId
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getActiveMaintenance(int $courtId)
  {
    return CourtMaintenance::where('court_id', $courtId)
      ->where('start_date', '<=', now())
      ->where('end_date', '>=', now())
      ->where('status', 'active')
      ->get();
  }

  /**
   * Check if court is under maintenance on a specific date
   *
   * @param int $courtId
   * @param \DateTime $date
   * @return bool
   */
  public function isUnderMaintenance(int $courtId, \DateTime $date): bool
  {
    return CourtMaintenance::where('court_id', $courtId)
      ->where('start_date', '<=', $date)
      ->where('end_date', '>=', $date)
      ->where('status', 'active')
      ->exists();
  }

  /**
   * Complete maintenance
   *
   * @param CourtMaintenance $maintenance
   * @return CourtMaintenance
   */
  public function completeMaintenance(CourtMaintenance $maintenance): CourtMaintenance
  {
    return tap($maintenance)->update([
      'status' => 'completed',
      'completed_at' => now(),
    ]);
  }

  /**
   * Get venue statistics
   *
   * @param Venue $venue
   * @return array
   */
  public function getVenueStats(Venue $venue): array
  {
    $courtsCount = $venue->courts()->count();
    $averageRating = $venue->courts()
      ->avg('average_rating') ?? 0;
    $totalBookings = DB::table('bookings')
      ->whereIn('court_id', $venue->courts()->pluck('id'))
      ->where('status', 'finished')
      ->count();

    return [
      'venue_id' => $venue->id,
      'total_courts' => $courtsCount,
      'average_rating' => round($averageRating, 2),
      'total_bookings' => $totalBookings,
      'total_images' => $venue->images()->count(),
      'created_at' => $venue->created_at,
      'updated_at' => $venue->updated_at,
    ];
  }

  /**
   * Toggle venue active status
   *
   * @param Venue $venue
   * @return Venue
   */
  public function toggleVenueStatus(Venue $venue): Venue
  {
    return tap($venue)->update([
      'is_active' => !$venue->is_active,
    ]);
  }

  /**
   * Get venues by city
   *
   * @param string $city
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getVenuesByCity(string $city)
  {
    return Venue::where('city', $city)
      ->where('is_active', true)
      ->with('courts', 'images')
      ->orderBy('name')
      ->get();
  }

  /**
   * Search venues by name or address
   *
   * @param string $searchTerm
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function searchVenues(string $searchTerm)
  {
    return Venue::where('is_active', true)
      ->where(function ($query) use ($searchTerm) {
        $query->where('name', 'like', "%{$searchTerm}%")
          ->orWhere('address', 'like', "%{$searchTerm}%")
          ->orWhere('city', 'like', "%{$searchTerm}%");
      })
      ->with('courts', 'images')
      ->orderBy('name')
      ->get();
  }
}
