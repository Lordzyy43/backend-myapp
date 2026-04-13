<?php

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/
// is_admin middleware
use App\Http\Middleware\IsAdmin;


// Auth
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Auth\EmailVerificationController;

// Public
use App\Http\Controllers\API\V1\Public\SportController;
use App\Http\Controllers\API\V1\Public\VenueController;
use App\Http\Controllers\API\V1\Public\CourtController;
use App\Http\Controllers\API\V1\Public\TimeSlotController;
use App\Http\Controllers\API\V1\Public\AvailabilityController;
use App\Http\Controllers\API\V1\Public\ReviewController;
use App\Http\Controllers\API\V1\Public\PromoController;

// User
use App\Http\Controllers\API\V1\User\BookingController;
use App\Http\Controllers\API\V1\User\PaymentController;
use App\Http\Controllers\API\V1\User\NotificationController;

// Admin
use App\Http\Controllers\API\V1\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\API\V1\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\API\V1\Admin\TimeSlotController as AdminTimeSlotController;
use App\Http\Controllers\API\V1\Admin\SportController as AdminSportController;
use App\Http\Controllers\API\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\API\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\API\V1\Admin\PromoController as AdminPromoController;

Route::prefix('v1')->group(function () {

  /*
  |--------------------------------------------------------------------------
  | PUBLIC ROUTES
  |--------------------------------------------------------------------------
  */
  Route::middleware('throttle:60,1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {

      Route::post('/register', [AuthController::class, 'register']);
      Route::post('/login', [AuthController::class, 'login']);

      // 🔥 EMAIL VERIFICATION (NO AUTH, SIGNED URL)
      Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    });

    /*
    |--------------------------------------------------------------------------
    | PUBLIC DATA
    |--------------------------------------------------------------------------
    */
    Route::get('/sports', [SportController::class, 'index']);

    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/venues/{id}', [VenueController::class, 'show']);

    Route::get('/venues/{venue_id}/courts', [CourtController::class, 'byVenue']);
    Route::get('/courts/{id}', [CourtController::class, 'show']);

    Route::get('/courts/{court_id}/timeslots', [TimeSlotController::class, 'byCourt']);

    Route::get('/availability', [AvailabilityController::class, 'index']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::get('/courts/{courtId}/reviews', [ReviewController::class, 'getByCourt']);
    Route::get('/courts/{courtId}/reviews/rating/{rating}', [ReviewController::class, 'getByRating']);
    Route::get('/courts/{courtId}/reviews/distribution', [ReviewController::class, 'getRatingDistribution']);
    Route::get('/courts/{courtId}/reviews/stats', [ReviewController::class, 'getStatistics']);
    Route::get('/venues/{venueId}/reviews', [ReviewController::class, 'getByVenue']);

    Route::get('/promos', [PromoController::class, 'index']);
    Route::get('/promos/expiring', [PromoController::class, 'expiringPromos']);
    Route::post('/promos/validate', [PromoController::class, 'validate']);
    Route::get('/promos/{code}', [PromoController::class, 'show']);
  });

  /*
  |--------------------------------------------------------------------------
  | SYSTEM ROUTES
  |--------------------------------------------------------------------------
  */
  Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

  /*
  |--------------------------------------------------------------------------
  | AUTHENTICATED USER
  |--------------------------------------------------------------------------
  */
  Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */
    Route::prefix('me')->group(function () {
      Route::get('/', [AuthController::class, 'me']);
      Route::put('/', [AuthController::class, 'update']);
      Route::post('/logout', [AuthController::class, 'logout']);
      Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    /*
    |--------------------------------------------------------------------------
    | EMAIL VERIFICATION (AUTH USER)
    |--------------------------------------------------------------------------
    */
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend']);

    /*
    |--------------------------------------------------------------------------
    | BOOKINGS
    |--------------------------------------------------------------------------
    */
    Route::prefix('bookings')->group(function () {
      Route::get('/', [BookingController::class, 'index']);
      Route::post('/', [BookingController::class, 'store']);
      Route::get('/{id}', [BookingController::class, 'show']);
      Route::patch('/{id}/cancel', [BookingController::class, 'cancel']);
    });

    /*
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
      Route::get('/', [PaymentController::class, 'index']);
      Route::get('/{id}', [PaymentController::class, 'show']);
      Route::post('/', [PaymentController::class, 'store']);
      Route::patch('/{id}/cancel', [PaymentController::class, 'cancel']);
      Route::patch('/{id}/confirm', [PaymentController::class, 'confirm']); // optional
    });

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
      Route::get('/', [NotificationController::class, 'index']);
      Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
      Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
      Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
      Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
    /*
    |--------------------------------------------------------------------------
    | REVIEWS
    |--------------------------------------------------------------------------
    */
    Route::prefix('reviews')->group(function () {
      Route::post('/', [ReviewController::class, 'store']);
      Route::get('/my-reviews', [ReviewController::class, 'getUserReviews']);
      Route::get('/{id}', [ReviewController::class, 'show']);
      Route::patch('/{id}', [ReviewController::class, 'update']);
      Route::delete('/{id}', [ReviewController::class, 'destroy']);
      Route::post('/{id}/helpful', [ReviewController::class, 'markHelpful']);
      Route::post('/{id}/report', [ReviewController::class, 'report']);
    });

    /*
  |--------------------------------------------------------------------------
  | ADMIN ROUTES
  |--------------------------------------------------------------------------
  */
    Route::prefix('admin')
      ->middleware(['auth:sanctum', 'is_admin'])
      ->group(function () {

        /*
      |--------------------------------------------------------------------------
      | RESOURCE MANAGEMENT
      |--------------------------------------------------------------------------
      */
        Route::apiResource('venues', AdminVenueController::class);
        Route::apiResource('courts', AdminCourtController::class);
        Route::apiResource('timeslots', AdminTimeSlotController::class);
        Route::apiResource('sports', AdminSportController::class);
        Route::apiResource('promos', AdminPromoController::class);

        /*
      |--------------------------------------------------------------------------
      | PROMO MANAGEMENT
      |--------------------------------------------------------------------------
      */
        Route::prefix('promos')->group(function () {
          Route::post('/deactivate-expired', [AdminPromoController::class, 'deactivateExpired']);
          Route::get('/stats', [AdminPromoController::class, 'statistics']);
        });

        /*
      |--------------------------------------------------------------------------
      | BOOKING MANAGEMENT
      |--------------------------------------------------------------------------
      */
        Route::prefix('bookings')->group(function () {
          Route::get('/', [AdminBookingController::class, 'index']);
          Route::get('/reports', [AdminBookingController::class, 'report']);
          Route::patch('/{id}/approve', [AdminBookingController::class, 'approve']);
          Route::patch('/{id}/reject', [AdminBookingController::class, 'reject']);
          Route::patch('/{id}/finish', [AdminBookingController::class, 'finish']);
        });

        /*
      |--------------------------------------------------------------------------
      | PAYMENT MANAGEMENT
      |--------------------------------------------------------------------------
      */
        Route::prefix('payments')->group(function () {
          Route::get('/', [AdminPaymentController::class, 'index']);
          Route::get('/{id}', [AdminPaymentController::class, 'show']);
          Route::patch('/{id}/approve', [AdminPaymentController::class, 'approve']);
          Route::patch('/{id}/reject', [AdminPaymentController::class, 'reject']);
          Route::patch('/{id}/expire', [AdminPaymentController::class, 'expire']); // optional
        });

        /*
      |--------------------------------------------------------------------------
      | USER MANAGEMENT
      |--------------------------------------------------------------------------
      */
        Route::get('/users', [AdminBookingController::class, 'usersIndex']);
      });
  });
});
