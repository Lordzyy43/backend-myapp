<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $booking_code
 * @property int $user_id
 * @property int $court_id
 * @property \Illuminate\Support\Carbon $booking_date
 * @property string $start_time
 * @property string $end_time
 * @property int $status_id
 * @property numeric $total_price
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Court|null $court
 * @property-read \App\Models\Payment|null $payment
 * @property-read \App\Models\BookingStatus $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeSlot> $timeSlots
 * @property-read int|null $time_slots_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBookingCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBookingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUserId($value)
 */
	class Booking extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $status_name
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus whereStatusName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingStatus whereUpdatedAt($value)
 */
	class BookingStatus extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property int $court_id
 * @property \Illuminate\Support\Carbon $booking_date
 * @property int $time_slot_id
 * @property-read \App\Models\Booking $booking
 * @property-read \App\Models\Court|null $court
 * @property-read \App\Models\TimeSlot $timeSlot
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot byCourt($courtId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot byDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot bySlots($slotIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot whereBookingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingTimeSlot whereTimeSlotId($value)
 */
	class BookingTimeSlot extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $venue_id
 * @property int $sport_id
 * @property string $name
 * @property numeric $price_per_hour
 * @property string $status
 * @property string|null $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourtImage> $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourtMaintenance> $maintenances
 * @property-read int|null $maintenances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\Sport $sport
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeSlot> $timeSlots
 * @property-read int|null $time_slots_count
 * @property-read \App\Models\Venue $venue
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court wherePricePerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereSportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court withoutTrashed()
 */
	class Court extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $court_id
 * @property string $image_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Court|null $court
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtImage whereUpdatedAt($value)
 */
	class CourtImage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $court_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Court|null $court
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance activeOnDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourtMaintenance whereUpdatedAt($value)
 */
	class CourtMaintenance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property string|null $action_url
 * @property array<array-key, mixed>|null $data
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $notifiable
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereActionUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUserId($value)
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property string $payment_method
 * @property numeric $amount
 * @property string|null $payment_proof
 * @property int $payment_status_id
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read \App\Models\PaymentStatus $status
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentProof($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $status_name
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus whereStatusName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentStatus whereUpdatedAt($value)
 */
	class PaymentStatus extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $promo_code
 * @property string|null $description
 * @property string $discount_type
 * @property numeric $discount_value
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property int|null $usage_limit
 * @property int $used_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereDiscountValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo wherePromoCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereUsageLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Promo whereUsedCount($value)
 */
	class Promo extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $booking_id
 * @property int $user_id
 * @property int $venue_id
 * @property int $court_id
 * @property int $rating
 * @property string|null $review_text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read \App\Models\Court|null $court
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Venue $venue
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereReviewText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review whereVenueId($value)
 */
	class Review extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $role_name
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $image
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Court> $courts
 * @property-read int|null $courts_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sport whereUpdatedAt($value)
 */
	class Sport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $start_time
 * @property string $end_time
 * @property int|null $order_index
 * @property bool $is_active
 * @property string|null $label
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereOrderIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeSlot whereUpdatedAt($value)
 */
	class TimeSlot extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property int $role_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Promo> $promoUsages
 * @property-read int|null $promo_usages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\Role $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Venue> $venues
 * @property-read int|null $venues_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property string $address
 * @property string $city
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Court> $courts
 * @property-read int|null $courts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VenueImage> $images
 * @property-read int|null $images_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VenueOperatingHour> $operatingHours
 * @property-read int|null $operating_hours_count
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Promo> $promos
 * @property-read int|null $promos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Review> $reviews
 * @property-read int|null $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venue whereUpdatedAt($value)
 */
	class Venue extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $venue_id
 * @property string $image_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Venue $venue
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueImage whereVenueId($value)
 */
	class VenueImage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $venue_id
 * @property int $day_of_week
 * @property string $open_time
 * @property string $close_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Venue $venue
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereCloseTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereOpenTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueOperatingHour whereVenueId($value)
 */
	class VenueOperatingHour extends \Eloquent {}
}

