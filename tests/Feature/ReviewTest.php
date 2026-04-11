<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Court;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Models\Sport;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class ReviewTest extends TestCase
{
  use RefreshDatabase, WithFaker;

  protected $user;
  protected $owner;
  protected $court;
  protected $venue;
  protected $finishedBooking;
  protected $pendingBooking;
  protected $reviewService;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. SEED BOOKING STATUS
    $statuses = ['pending', 'confirmed', 'cancelled', 'expired', 'finished'];
    foreach ($statuses as $status) {
      BookingStatus::firstOrCreate(['status_name' => $status]);
    }

    // 2. CREATE ACTORS
    $this->user = User::factory()->create(['role_id' => 3]); // Regular user
    $this->owner = User::factory()->create(['role_id' => 2]); // Venue owner

    // 3. CREATE INFRASTRUCTURE
    $sport = Sport::firstOrCreate(['name' => 'Badminton']);

    $this->venue = Venue::create([
      'owner_id' => $this->owner->id,
      'sport_id' => $sport->id,
      'name' => 'Review Test Venue',
      'address' => 'Test Address',
      'city' => 'Jakarta',
      'phone' => '08123456789',
      'email' => 'review-venue-' . rand(1, 99) . '@test.com',
      'slug' => 'review-venue-test-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    $this->court = Court::create([
      'venue_id' => $this->venue->id,
      'sport_id' => $sport->id,
      'name' => 'Court A',
      'price_per_hour' => 75000,
      'description' => 'Premium court',
      'slug' => 'court-a-' . strtolower(bin2hex(random_bytes(3)))
    ]);

    // 4. CREATE TIME SLOTS
    TimeSlot::firstOrCreate(['start_time' => '09:00', 'end_time' => '10:00', 'order_index' => 1, 'is_active' => true]);
    TimeSlot::firstOrCreate(['start_time' => '10:00', 'end_time' => '11:00', 'order_index' => 2, 'is_active' => true]);

    // 5. CREATE FINISHED BOOKING (for reviews)
    $this->finishedBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'REVIEW-' . rand(10000, 99999),
      'booking_date' => now()->subDays(1)->toDateString(),
      'status_id' => BookingStatus::where('status_name', 'finished')->value('id'),
      'total_price' => 150000,
      'expires_at' => now()->subDays(1),
      'booking_source' => 'mobile'
    ]);

    // 6. CREATE PENDING BOOKING (should NOT be reviewable)
    $this->pendingBooking = Booking::create([
      'user_id' => $this->user->id,
      'court_id' => $this->court->id,
      'booking_code' => 'PENDING-' . rand(10000, 99999),
      'booking_date' => now()->addDays(1)->toDateString(),
      'status_id' => BookingStatus::where('status_name', 'pending')->value('id'),
      'total_price' => 150000,
      'expires_at' => now()->addDays(1),
      'booking_source' => 'mobile'
    ]);

    // 7. SERVICE INSTANCE
    $this->reviewService = new ReviewService();
  }

  #[Test]
  public function user_can_submit_review_for_finished_booking()
  {
    $review = $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      5,
      'Excellent court and service!'
    );

    $this->assertNotNull($review->id);
    $this->assertEquals($review->user_id, $this->user->id);
    $this->assertEquals($review->rating, 5);
    $this->assertEquals($review->booking_id, $this->finishedBooking->id);
    $this->assertDatabaseHas('reviews', [
      'booking_id' => $this->finishedBooking->id,
      'user_id' => $this->user->id,
      'rating' => 5
    ]);
  }

  #[Test]
  public function user_cannot_review_non_finished_booking()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('User is not eligible to review this booking');

    $this->reviewService->createBookingReview(
      $this->pendingBooking,
      $this->user,
      5,
      'Great experience'
    );
  }

  #[Test]
  public function user_cannot_review_others_booking()
  {
    $otherUser = User::factory()->create(['role_id' => 3]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('User is not eligible to review this booking');

    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $otherUser,
      5,
      'Nice court'
    );
  }

  #[Test]
  public function review_with_invalid_rating_throws_exception()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Rating must be between 1 and 5');

    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      6,
      'Invalid rating'
    );
  }

  #[Test]
  public function review_with_zero_rating_throws_exception()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Rating must be between 1 and 5');

    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      0,
      'Zero rating'
    );
  }

  #[Test]
  public function user_cannot_submit_duplicate_review()
  {
    // First review
    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      5,
      'First review'
    );

    // Second review should fail
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('User has already reviewed this booking');

    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      4,
      'Second review attempt'
    );
  }

  #[Test]
  public function can_check_if_user_has_reviewed_booking()
  {
    $hasReviewed = $this->reviewService->hasReviewedBooking(
      $this->finishedBooking,
      $this->user
    );
    $this->assertFalse($hasReviewed);

    // Create review
    $this->reviewService->createBookingReview(
      $this->finishedBooking,
      $this->user,
      5,
      'Good experience'
    );

    // Check again
    $hasReviewed = $this->reviewService->hasReviewedBooking(
      $this->finishedBooking,
      $this->user
    );
    $this->assertTrue($hasReviewed);
  }

  #[Test]
  public function review_rating_updates_court_average_rating()
  {
    // This test is skipped because the courts table doesn't have average_rating column
    // This is a planned feature for Phase 2 (admin panel with court analytics)
    $this->markTestSkipped('average_rating column not yet implemented in courts table');
  }
}
