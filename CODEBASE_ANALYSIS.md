# ApkBooking Backend - Comprehensive Codebase Analysis

**Analysis Date:** April 3, 2026  
**Framework:** Laravel 11  
**Project Type:** Venue/Court Booking System API

---

## TABLE OF CONTENTS

1. [CRITICAL BUGS & ISSUES](#critical-bugs--issues)
2. [CORE BOOKING SYSTEM ANALYSIS](#core-booking-system-analysis)
3. [CORE PAYMENT SYSTEM ANALYSIS](#core-payment-system-analysis)
4. [AVAILABILITY & SLOT LOGIC ANALYSIS](#availability--slot-logic-analysis)
5. [SECURITY & AUTHORIZATION ANALYSIS](#security--authorization-analysis)
6. [DATA CONSISTENCY & LOCKS](#data-consistency--locks)
7. [EVENT SYSTEM & NOTIFICATIONS](#event-system--notifications)
8. [STRENGTHS & BEST PRACTICES](#strengths--best-practices)
9. [WEAKNESSES & RISKS](#weaknesses--risks)
10. [PERFORMANCE CONCERNS](#performance-concerns)
11. [MISSING TEST COVERAGE](#missing-test-coverage)

---

## CRITICAL BUGS & ISSUES

### 🔴 BUG #1: StorePaymentRequest Authorization Hardcoded to FALSE

**File:** `app/Http/Requests/StorePaymentRequest.php` | **Line 14**

```php
public function authorize(): bool
{
    return false;  // ❌ BLOCKS ALL PAYMENT CREATIONS
}
```

**Impact:**

- **CRITICAL**: All payment creation requests will be rejected with 403 Forbidden
- User cannot create payments at all
- This completely breaks the payment flow

**Fix Required:**

```php
public function authorize(): bool
{
    $booking = Booking::find($this->booking_id);
    return $booking && $booking->user_id === auth()->id();
}
```

---

### 🔴 BUG #2: Missing `reject()` Method in BookingService

**File:** `app/Http/Controllers/API/V1/Admin/BookingController.php` | **Line 56**

```php
public function reject($id)
{
    return $this->handleAction($id, 'reject', 'Booking berhasil ditolak');
}
```

The controller calls `$service->reject()` but `BookingService` doesn't have this method (only: `create()`, `cancel()`, `approve()`, `finish()`).

**File:** `app/Services/BookingService.php` | Missing method

**Impact:**

- **FATAL ERROR**: Calling undefined method triggers `BadMethodCallException`
- Admin cannot reject pending bookings
- No error handling will catch this gracefully

**Fix Required:** Add to `BookingService`:

```php
public function reject(Booking $booking): Booking
{
    return DB::transaction(function () use ($booking) {
        $booking->lockForUpdate();

        if ($booking->status_id !== BookingStatus::pending()) {
            throw new Exception('Hanya booking pending yang bisa ditolak');
        }

        $booking->update([
            'status_id' => BookingStatus::cancelled()
        ]);

        // Release slots
        $booking->timeSlots()->detach();

        event(new \App\Events\BookingRejected($booking));

        return $booking;
    });
}
```

---

### 🔴 BUG #3: PaymentStatus Method Name Typo

**File:** `app/Models/PaymentStatus.php` | **Line 36**

```php
public static function cencelled()  // ❌ Typo: "cencelled" not "cancelled"
{
    return self::where('status_name', 'cenceled')->value('id');  // Also typo in DB lookup
}
```

**File:** `app/Http/Controllers/API/V1/User/PaymentController.php` | **Line 159**

```php
$payment->update([
    'payment_status_id' => PaymentStatus::cenceled()  // Wrong method call
]);
```

**Impact:**

- Payment cancellation fails or uses wrong method name
- Inconsistent naming convention

**Fix:**

```php
public static function cancelled()
{
    return self::where('status_name', 'cancelled')->value('id');
}
```

---

### 🟡 BUG #4: lockForUpdate() Result Not Reassigned

**File:** `app/Services/BookingService.php` | **Line 106**

```php
$booking->lockForUpdate();  // ❌ Result not reassigned

if (!in_array($booking->status_id, [
    BookingStatus::pending(),
    BookingStatus::confirmed()
])) {
    throw new Exception('Booking tidak bisa dibatalkan');
}
```

**Impact:**

- Lock is acquired but booking is not re-fetched
- Working with potentially stale data
- Race condition if booking was updated between retrieval and lock

**Fix:**

```php
$booking = $booking->lockForUpdate()->fresh();
```

---

### 🟡 BUG #5: PaymentExpired Event Not in EventServiceProvider

**File:** `app/Providers/EventServiceProvider.php` | **Line 56-60**

Event is referenced in code but not registered in listener mapping:

```php
// PaymentExpired is NOT listed here:
protected $listen = [
    BookingCreated::class => [...],
    BookingApproved::class => [...],
    // Missing: PaymentExpired::class => [SendPaymentExpiredNotification::class]
];
```

**File:** `app/Console/Commands/ExpireBookings.php` | **Line 52**

Events are dispatched but listener won't be called.

**Fix:**

```php
protected $listen = [
    // ... existing events ...
    PaymentExpired::class => [
        SendPaymentExpiredNotification::class,
    ],
];
```

---

## CORE BOOKING SYSTEM ANALYSIS

### Architecture Overview

**Models:**

- `Booking` (main booking record)
- `BookingStatus` (pending, confirmed, cancelled, expired, finished)
- `BookingTimeSlot` (pivot table: booking → time slots)
- `TimeSlot` (time blocks: 8am-9am, 9am-10am, etc)
- `Court` (the venue court being booked)

### Booking Creation Flow

**File:** `app/Http/Controllers/API\V1\User\BookingController.php` | **Line 47-59**

```php
public function store(StoreBookingRequest $request)
{
    $booking = $this->bookingService->create(
        auth()->user(),
        $request->validated()
    );
    return $this->created($booking, 'Booking berhasil');
}
```

**File:** `app/Services/BookingService.php` | **Line 17-97**

**Process:**

1. Get and lock TimeSlot records (prevents slot deletion during booking)
2. Check double-booking via `BookingTimeSlot::isBooked()`
3. Validate court price exists
4. Create booking with `pending` status and 10-minute expiry
5. Attach time slots to booking via pivot table
6. Apply promo discount (with lock to prevent race condition)
7. Set expiry via `Booking::setExpiry(10 minutes)`
8. Fire `BookingCreated` event → triggers notification listener

**Transaction Level:** ✅ Full transaction wrap (atomic)

---

### Double-Booking Prevention (ANTI-TABRAKAN)

**File:** `app/Models/BookingTimeSlot.php` | **Line 67-82**

```php
public static function isBooked($courtId, $date, $slotIds)
{
    $activeStatusIds = [BookingStatus::pending(), BookingStatus::confirmed()];

    return self::byCourt($courtId)
        ->byDate($date)
        ->bySlots($slotIds)
        ->whereHas('booking', function ($q) use ($activeStatusIds) {
            $q->whereIn('status_id', $activeStatusIds)
                ->where(function ($q2) {
                    $q2->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        })
        ->exists();
}
```

**Strengths:**

- ✅ Considers only active bookings (pending/confirmed)
- ✅ Respects expiry (expired bookings are ignored)
- ✅ Combines with `lockForUpdate()` for race safety
- ✅ Database-level unique constraint: `unique(['court_id', 'booking_date', 'time_slot_id'])`

**File:** `database/migrations/2026_03_18_055241_create_booking_time_slots_table.php` | **Line 27**

```php
$table->unique(['court_id', 'booking_date', 'time_slot_id']);
```

---

### Status Transitions & Business Rules

**File:** `app/Services/BookingService.php`

**Allowed Transitions:**

| Current Status    | Action          | New Status | Who   | Conditions            |
| ----------------- | --------------- | ---------- | ----- | --------------------- |
| pending           | cancel (user)   | cancelled  | User  | Not past booking_date |
| pending           | approve (admin) | confirmed  | Admin | Not expired           |
| confirmed         | finish (admin)  | finished   | Admin | After booking_date    |
| pending/confirmed | expire (auto)   | expired    | Cron  | expires_at <= now()   |

**File:** `app/Console/Commands/ExpireBookings.php`

Command runs periodically to auto-expire pending bookings:

```bash
php artisan booking:expire
```

**Issues Found:**

❌ **Missing scheduler registration**: No evidence of command being scheduled in `app/Console/Kernel.php` or `routes/console.php`

---

### Booking Query Indexes & Performance

**File:** `database/migrations/2026_03_16_051443_create_bookings_table.php`

```php
$table->index(['court_id', 'booking_date']);      // For availability queries
$table->index(['user_id', 'booking_date']);       // For user history
$table->index(['status_id']);                     // For filtering
```

✅ **Good:** Indexes on critical query paths

---

## CORE PAYMENT SYSTEM ANALYSIS

### Architecture Overview

**Models:**

- `Payment` (payment record linked to booking)
- `PaymentStatus` (pending, paid, cancelled, failed, expired)
- Relationship: `Booking hasOne Payment`

### Payment Creation Flow

**File:** `app/Http/Controllers/API\V1\User\PaymentController.php` | **Line 37-73**

```php
public function store(StorePaymentRequest $request)
{
    $payment = DB::transaction(function () use ($validated, $user) {

        $booking = Booking::where('id', $validated['booking_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // Validations:
        // - Booking not expired
        // - Payment doesn't already exist
        // - Booking is pending status

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_method' => $validated['payment_method'],
            'amount' => $booking->total_price,
            'payment_status_id' => PaymentStatus::pending(),
            'expired_at' => $booking->expires_at,
        ]);

        return $payment->load(['booking', 'status']);
    });

    event(new \App\Events\PaymentCreated($payment));
    return $this->created($payment, 'Payment berhasil dibuat');
}
```

**Issues Found:**

🔴 **StorePaymentRequest::authorize()** returns `false` (Bug #1) - **ALL PAYMENTS BLOCKED**

---

### Payment Confirmation (Success)

**File:** `app/Http/Controllers/API\V1\User\PaymentController.php` | **Line 76-98**

```php
public function confirm($id)
{
    $payment = DB::transaction(function () use ($id) {
        $payment = Payment::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        // Validates: not already paid, not expired

        $payment->markAsPaid();  // Sets paid_at, status=paid

        $payment->booking->update([
            'status_id' => BookingStatus::confirmed(),
            'expires_at' => null
        ]);

        return $payment->load(['booking', 'status']);
    });

    event(new \App\Events\PaymentSuccess($payment));
    return $this->success($payment, 'Pembayaran berhasil dikonfirmasi');
}
```

**Process:**

1. Lock payment row
2. Validate payment not already paid
3. Validate payment not expired
4. Call `Payment::markAsPaid()` which:
    - Sets `paid_at = now()`
    - Updates `payment_status_id` to 'paid'
    - **Also updates booking status to confirmed and clears expires_at**
5. Fire `PaymentSuccess` event

**File:** `app/Models/Payment.php` | **Line 48-60**

```php
public function markAsPaid(): void
{
    $this->update([
        'payment_status_id' => PaymentStatus::paid(),
        'paid_at' => now(),
    ]);

    // 🔥 update booking juga
    $this->booking->update([
        'status_id' => BookingStatus::confirmed(),
        'expires_at' => null
    ]);
}
```

⚠️ **Issue**: Updates happen both in `markAsPaid()` AND in controller. Redundant updates:

**File:** `app/Http/Controllers/API\V1\User\PaymentController.php` | **Line 86-89**

```php
$payment->booking->update([
    'status_id' => BookingStatus::confirmed(),
    'expires_at' => null
]);
```

---

### Payment Cancellation

**File:** `app/Http/Controllers/API\V1\User\PaymentController.php` | **Line 117-138**

```php
public function cancel($id)
{
    DB::transaction(function () use ($id) {
        $payment = Payment::where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->authorize('cancel', $payment);

        if ($payment->isPaid()) {
            abort(400, 'Tidak bisa cancel payment yang sudah dibayar');
        }

        $payment->update([
            'payment_status_id' => PaymentStatus::cenceled()  // ❌ Bug #3: typo
        ]);

        $payment->booking->update([
            'status_id' => BookingStatus::cancelled()
        ]);

        event(new \App\Events\PaymentCancelled($payment));
    });

    return $this->success(null, 'Payment berhasil dibatalkan');
}
```

⚠️ **Issue**: Cancels payment WITHOUT releasing booking slots. Booking stays cancelled but slots remain attached?

---

### Payment Admin Endpoints

**File:** `app/Http/Controllers/API\V1\Admin\PaymentController.php`

**approve()** - Line 43-78

- Marks payment as paid
- Confirms booking
- Fires `PaymentSuccess` event

**reject()** / **fail()** - Line 81-120

- Sets payment to 'failed'
- Cancels booking
- Fires `PaymentFailed` event

**expire()** - Line 123-154

- Sets payment to 'expired'
- Expires booking
- Fires `PaymentExpired` event

---

## AVAILABILITY & SLOT LOGIC ANALYSIS

### AvailabilityController - Core Query

**File:** `app/Http/Controllers\API\V1\Public\AvailabilityController.php` | **Full file**

**Endpoint:** `GET /availability?court_id={id}&date={date}`

**Priority-Based Availability Logic:**

```php
public function index(Request $request)
{
    // 1. Validate court_id & date
    $court = Court::with('venue')->findOrFail($validated['court_id']);
    $date = Carbon::parse($validated['date']);

    // 2. Check operating hours
    $operating = VenueOperatingHour::where('venue_id', $court->venue_id)
        ->where('day_of_week', $date->dayOfWeek)
        ->first();

    if (!$operating) {
        return unavailable ('venue tutup');  // Closed on this day
    }

    // 3. Check maintenance
    $isMaintenance = CourtMaintenance::where('court_id', $court->id)
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->exists();

    // 4. Get booked slots
    $bookedSlotIds = BookingTimeSlot::getBookedSlots($court->id, $date->toDateString());

    // 5. Get all active slots
    $slots = TimeSlot::active()->orderBy('order_index')->get();

    // 6. Map to availability with priority
    return $slots->map(function ($slot) use (...) {
        if ($isMaintenance)
            return unavailable('maintenance');
        elseif (in_array($slot->id, $bookedSlotIds))
            return unavailable('booked');
        elseif ($slot outside operating hours)
            return unavailable('outside_operating_hours');
        elseif ($slot is past time today)
            return unavailable('past_time');
        else
            return available();
    });
}
```

**Strengths:**
✅ Clear priority/ordering
✅ Uses `getBookedSlots()` which respects expiry
✅ Validates operating hours per venue
✅ Blocks maintenance dates

**Weaknesses:**

❌ **No transaction wrapping** - Between checking booked slots and returning response, another booking could be created → race condition in UI

❌ **N+1 potential** - If multiple time slots returned, each slot object loaded

❌ **Timezone not explicit** - Uses `now()` and `-today()` without timezone context

---

### TimeSlot Model

**File:** `app/Models/TimeSlot.php`

```php
class TimeSlot extends Model
{
    protected $fillable = [
        'start_time',
        'end_time',
        'order_index',
        'is_active',
        'label',
    ];

    public function getDurationMinutes()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    public function getFormattedLabel()
    {
        return $this->start_time . ' - ' . $this->end_time;
    }
}
```

⚠️ **Issue**: `start_time` and `end_time` stored as strings (not time type) - risky for comparisons

---

## SECURITY & AUTHORIZATION ANALYSIS

### Authentication

**Middleware:** `auth:sanctum` (Sanctum tokens)

**Routes** that require auth:

- All `/me/*` endpoints
- All user booking endpoints
- All user payment endpoints
- All admin endpoints

### Authorization

#### BookingPolicy

**File:** `app/Policies/BookingPolicy.php`

```php
public function view(User $user, Booking $booking): bool
{
    if ($booking->user_id === $user->id) return true;  // User can view own
    return $user->role->role_name === 'admin';         // Admin can view all
}

public function cancel(User $user, Booking $booking): bool
{
    return $booking->user_id === $user->id
        && in_array($booking->status_id, [
            BookingStatus::pending(),
            BookingStatus::confirmed()
        ]);
}

public function approve(User $user, Booking $booking): bool
{
    return $user->role->role_name === 'admin'
        && $booking->status_id === BookingStatus::pending();
}

public function reject(User $user, Booking $booking): bool
{
    return $user->role->role_name === 'admin'
        && $booking->status_id === BookingStatus::pending();
}

public function finish(User $user, Booking $booking): bool
{
    return $user->role->role_name === 'admin'
        && $booking->status_id === BookingStatus::confirmed();
}
```

**Usage Example:**

**File:** `app/Http/Controllers\API\V1\User\BookingController.php` | **Line 69**

```php
public function show(string $id)
{
    $booking = Booking::with(['court', 'timeSlots', 'status'])
        ->findOrFail($id);

    $this->authorize('view', $booking);  // ✅ Checks policy

    return $this->success($booking, 'Detail booking berhasil diambil');
}
```

✅ **Strengths:**

- Policies used consistently
- Role-based + owner-based checks
- Clear business rule enforcement

❌ **Weaknesses:**

- Policy uses string comparison `$user->role->role_name === 'admin'` - could use enum or constant
- No middleware for admin-only routes (relies on policy checks)

---

### Request Validation

**File:** `app/Http/Requests/StoreBookingRequest.php`

```php
public function authorize(): bool
{
    return true;  // ✅ Delegates to Policy
}

public function rules(): array
{
    return [
        'court_id' => 'required|exists:courts,id',                    // ✅ Validates court exists
        'booking_date' => 'required|date|after_or_equal:today',       // ✅ No past dates
        'slot_ids' => 'required|array|min:1',
        'slot_ids.*' => 'exists:time_slots,id',                       // ✅ Validates each slot
        'promo_code' => 'nullable|string|max:50',
    ];
}
```

✅ **Good validation rules** - Prevents invalid data from reaching service layer

---

**File:** `app/Http/Requests/StorePaymentRequest.php`

```php
public function authorize(): bool
{
    return false;  // 🔴 BUG #1: BLOCKS ALL PAYMENTS
}

public function rules(): array
{
    return [
        'booking_id' => 'required|exists:bookings,id',
        'payment_method' => 'required|string|max:50',
    ];
}
```

---

### Middleware

**File:** `app/Http/Middleware/IsAdmin.php`

Only middleware found is `IsAdmin.php`. Likely used as:

```php
Route::middleware('auth:sanctum', 'is_admin')->group(function () {
    // admin routes
});
```

---

## DATA CONSISTENCY & LOCKS

### Booking Creation Transaction

**File:** `app/Services/BookingService.php` | **Line 17-97**

```php
public function create($user, array $data): Booking
{
    return DB::transaction(function () use ($user, $data) {
        // 1. Lock TimeSlot (prevents deletion)
        $slots = TimeSlot::whereIn('id', $data['slot_ids'])
            ->lockForUpdate()
            ->get();

        // 2. Check availability (with locked rows)
        if (BookingTimeSlot::isBooked(...)) throw Exception();

        // 3. Create booking
        $booking = Booking::create([...]);

        // 4. Attach slots
        foreach ($data['slot_ids'] as $slotId) {
            $booking->timeSlots()->attach($slotId, [...]);
        }

        // 5. Lock and apply promo
        $promo = Promo::where('promo_code', $data['promo_code'])
            ->lockForUpdate()
            ->first();
        if ($promo && $promo->isValid()) {
            $booking->update([...]);
            $promo->markUsed();
        }

        // 6. Set expiry
        $booking->setExpiry();
        $booking->save();

        // 7. Fire event
        event(new BookingCreated($booking));

        return $booking;
    });
}
```

✅ **Strengths:**

- Full transaction wrap
- Locks acquired before checks
- Prevents race conditions on:
    - TimeSlot deletion
    - Double-booking
    - Promo over-usage

---

### Payment Creation Transaction

**File:** `app/Http/Controllers\API\V1\User\PaymentController.php` | **Line 43-73**

```php
$payment = DB::transaction(function () use ($validated, $user) {
    $booking = Booking::where('id', $validated['booking_id'])
        ->lockForUpdate()
        ->firstOrFail();

    if ($booking->isExpired()) abort();
    if ($booking->payment) abort();
    if ($booking->status_id !== BookingStatus::pending()) abort();

    $payment = Payment::create([...]);
    return $payment;
});
```

✅ **Good:** Booking locked before payment creation

---

### Database Constraints

**Bookings Table Migration:**

```php
$table->foreignId('user_id')
    ->constrained('users')
    ->cascadeOnDelete();

$table->foreignId('court_id')
    ->constrained('courts')
    ->cascadeOnDelete();

$table->foreignId('status_id')
    ->constrained('booking_status');
```

⚠️ **Issue:** No ON DELETE constraint for `status_id` - if booking_status row deleted, booking orphaned

---

**Payments Table Migration:**

```php
$table->foreignId('booking_id')
    ->constrained()
    ->cascadeOnDelete();

$table->unique('booking_id');  // ✅ One payment per booking
```

✅ **Good:** Unique constraint + cascade delete

---

**BookingTimeSlots Pivot Migration:**

```php
$table->unique(['court_id', 'booking_date', 'time_slot_id']);
```

✅ **Excellent:** Database-level unique constraint prevents duplicate bookings even if application logic fails

---

## EVENT SYSTEM & NOTIFICATIONS

### Event Mapping

**File:** `app/Providers/EventServiceProvider.php` | **Line 31-71**

```php
protected $listen = [
    BookingCreated::class => [
        SendBookingCreatedNotification::class,
    ],

    BookingApproved::class => [
        SendBookingApprovedNotification::class,
    ],

    BookingRejected::class => [
        SendBookingRejectedNotification::class,
    ],

    BookingFinished::class => [
        SendBookingFinishedNotification::class,
    ],

    BookingExpired::class => [
        SendBookingExpiredNotification::class,
    ],

    PaymentCreated::class => [
        SendPaymentCreatedNotification::class,
    ],

    PaymentSuccess::class => [
        SendPaymentSuccessNotification::class,
    ],

    PaymentCancelled::class => [
        SendPaymentCancelledNotification::class,
    ],
];
```

⚠️ **Missing:** `PaymentExpired::class` not in listener map, but event fired in:

- `app/Console/Commands/ExpireBookings.php` line 52
- `app/Http/Controllers/API/V1/Admin/PaymentController.php` line 144

---

### Listener Implementation

**File:** `app/Listeners/SendBookingCreatedNotification.php`

```php
class SendBookingCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        NotificationService::send(
            $booking->user_id,
            'booking_created',
            'Booking Dibuat',
            'Silakan lanjutkan pembayaran',
            $booking
        );
    }

    public function failed(BookingCreated $event, \Throwable $exception): void
    {
        \Log::error('BookingCreated Notification Failed', [
            'booking_id' => $event->booking->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

✅ **Strengths:**

- Implements `ShouldQueue` for async processing
- Retry policy (3 tries, 10s backoff)
- Failed listener logging

---

### NotificationService

**File:** `app/Services/NotificationService.php`

```php
public static function send(
    $userId,
    $type,
    $title,
    $message,
    $notifiable = null,
    $actionUrl = null,
    $data = []
) {
    return Notification::create([
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'notifiable_id' => $notifiable?->id,
        'notifiable_type' => $notifiable ? get_class($notifiable) : null,
        'action_url' => $actionUrl,
        'data' => $data,
    ]);
}
```

⚠️ **Issue:** Only saves to `notifications` table - no actual push notifications, SMS, or email sent

---

## STRENGTHS & BEST PRACTICES

### 1. **Atomicity with Transactions** ✅

- All critical operations wrapped in `DB::transaction()`
- Booking creation, payment creation/confirmation all transactional
- Prevents partial state updates

**Examples:**

- `BookingService::create()` line 17
- `PaymentController::store()` line 43
- `ExpireBookings` command line 23

---

### 2. **Optimistic Locking with lockForUpdate()** ✅

- Used on TimeSlots, Bookings, Payments, Promos
- Prevents race conditions during concurrent updates
- Key for high-load scenarios

**Examples:**

- `BookingService::create()` line 24 (TimeSlots)
- `PaymentController::store()` line 50 (Booking)
- `PaymentController::confirm()` line 80 (Payment)

---

### 3. **Database-Level Constraints** ✅

- Unique constraint on `booking_time_slots(court_id, booking_date, time_slot_id)`
- Foreign keys with cascade deletes
- Status IDs enforced

**File:** `database/migrations/2026_03_18_055241_create_booking_time_slots_table.php`

---

### 4. **Policy-Based Authorization** ✅

- Centralized authorization logic in `BookingPolicy`
- Clear separation of concerns
- Easy to audit and modify permissions

**File:** `app/Policies/BookingPolicy.php`

---

### 5. **Service Pattern for Business Logic** ✅

- `BookingService` encapsulates all booking operations
- Prevents logic duplication between controllers
- Easier to test

**File:** `app/Services/BookingService.php`

---

### 6. **Event-Driven Architecture** ✅

- Decouples booking/payment logic from notifications
- Used for:
    - BookingCreated → SendNotification
    - PaymentSuccess → SendNotification
    - BookingExpired → Cleanup

---

### 7. **Queued Listeners** ✅

- Notifications processed async
- Retry logic with backoff
- Failed listener logging

**File:** `app/Listeners/SendBookingCreatedNotification.php`

---

### 8. **Validation with Custom Messages** ✅

- User-friendly error messages
- Validated at request layer
- Rules prevent invalid data reaching service

**File:** `app/Http/Requests/StoreBookingRequest.php`

---

### 9. **Clean Code Style** ✅

- Meaningful variable names
- Comments in Indonesian (consistent with locale)
- Clear method names (isSlotAvailable, isExpired, markAsPaid)

---

### 10. **Smart Booking Code Generation** ✅

**File:** `app/Models/Booking.php` | Line 71

```php
public static function generateBookingCode()
{
    return 'BOOK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    // Example: BOOK-20260403-XY9ZK
}
```

✅ Unique, memorable, date-prefixed

---

## WEAKNESSES & RISKS

### 1. 🔴 **CRITICAL: Authorization Broken in StorePaymentRequest**

- `authorize()` returns `false`
- All payment creation blocked
- See Bug #1 above

---

### 2. 🔴 **CRITICAL: Missing `reject()` Method in BookingService**

- Admin cannot reject bookings
- Will throw `BadMethodCallException`
- See Bug #2 above

---

### 3. 🟡 **Payment Status Typo**

- `cencelled()` method name is wrong
- Database lookup also has typo
- See Bug #3 above

---

### 4. 🟡 **Race Condition in AvailabilityController**

**File:** `app/Http/Controllers\API\V1\Public\AvailabilityController.php`

```php
// Check available slots
$bookedSlotIds = BookingTimeSlot::getBookedSlots($court->id, $date->toDateString());

// <-- RACE CONDITION: Another booking could be created here -->

// Return response
return $this->success(['slots' => $result]);
```

**Scenario:**

1. User checks availability → sees slot as available
2. Another user books that slot
3. First user attempts to book same slot → double-booking error

**Fix:** Wrap check in transaction or use pessimistic locking

---

### 5. 🟡 **Redundant Database Updates in Payment Confirmation**

**File:** `app/Models/Payment.php` | Line 48-60 AND `app/Http/Controllers\API\V1\User\PaymentController.php` | Line 86-89

Both `markAsPaid()` and controller update booking status:

```php
// In Payment model
$this->booking->update([
    'status_id' => BookingStatus::confirmed(),
    'expires_at' => null
]);

// AND in controller
$payment->booking->update([
    'status_id' => BookingStatus::confirmed(),
    'expires_at' => null
]);
```

**Issue:** Redundant, wasting DB calls

---

### 6. 🟡 **Missing ExpireBookings Scheduler**

**File:** `app/Console/Commands/ExpireBookings.php` exists but:

- No evidence of being scheduled in `app/Console/Kernel.php`
- Without scheduling, auto-expiry won't work
- Bookings will stay pending forever

**Fix Required:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('booking:expire')->everyMinute();
}
```

---

### 7. 🟡 **No Timezone Handling**

- Uses `now()` without timezone context
- Comparisons like `$date->isSameDay($today)` could fail during DST transitions
- Booking dates vs. current time ambiguous

**Recommendation:**

```php
// In AppServiceProvider
Date::useLocation('Asia/Jakarta');  // Or appropriate timezone

// Or explicit:
now()->setTimezone('Asia/Jakarta');
```

---

### 8. 🟡 **String-Based TimeSlot Storage**

**File:** `database/migrations/2026_03_18_055241_create_time_slots_table.php` (implied)

```php
$table->string('start_time');   // Stored as string like "08:00:00"
$table->string('end_time');     // Stored as string
```

**Issue:**

- String comparisons unreliable (e.g., "08:00" vs "08:00:00")
- Harder to calculate durations
- Time arithmetic requires parsing each time

**Better:** Use `time` column type

---

### 9. 🟡 **No Promo Validation in Admin Payment Paths**

**File:** `app/Http/Controllers\API\V1\Admin\PaymentController.php` | Line 43-78

Admin approving payment can:

- Bypass promo expiry checks
- Confirm payment for any amount
- No audit trail why amount changed

---

### 10. 🟡 **No Concurrency Lock On Promo Usage**

**File:** `app/Services/BookingService.php` | Line 63

```php
$promo = Promo::where('promo_code', $data['promo_code'])
    ->lockForUpdate()
    ->first();

if ($promo && $promo->isValid()) {
    // ... discount applied ...
    $promo->markUsed();  // increment('used_count')
}
```

⚠️ **Issue:** Between checking `usage_limit` and incrementing `used_count`, limit could be exceeded:

**Scenario:**

1. Promo has limit=5, used_count=4
2. User A checks: valid (4 < 5)
3. User B checks: valid (4 < 5)
4. User A books: used_count → 5
5. User B books: used_count → 6 ✗ **Over limit!**

**Better:** Use `increment()` with where clause:

```php
$updated = Promo::where('id', $promo->id)
    ->where('used_count', '<', DB::raw('usage_limit'))
    ->increment('used_count');

if (!$updated) throw new Exception('Promo limit exceeded');
```

---

### 11. 🟡 **StorePaymentRequest Missing Custom Messages**

**File:** `app/Http/Requests/StorePaymentRequest.php`

No custom messages defined. Users get generic Laravel messages.

**Compare to StoreBookingRequest:**

```php
public function messages(): array
{
    return [
        'court_id.required' => 'Court wajib dipilih',
        'court_id.exists' => 'Court tidak valid',
    ];
}
```

---

### 12. 🟡 **Null Pointer Risk in AvailabilityController**

**File:** `app/Http/Controllers\API\V1\Public\AvailabilityController.php` | Line 22-28

```php
$operating = VenueOperatingHour::where('venue_id', $court->venue_id)
    ->where('day_of_week', $dayOfWeek)
    ->first();

if (!$operating) {
    return $this->success([...], 'Venue tutup');
}

$openTime = Carbon::parse($operating->open_time);  // ✓ Safe after check
```

✅ **Good:** Null check exists, but could be more defensive with `->firstOrFail()`

---

### 13. 🟡 **Payment Cancellation Doesn't Release Slots**

**File:** `app/Http/Controllers\API\V1\User\PaymentController.php` | Line 117-138

When payment is cancelled:

```php
$payment->update(['payment_status_id' => PaymentStatus::cenceled()]);

$payment->booking->update(['status_id' => BookingStatus::cancelled()]);

// ❌ NO SLOT RELEASE
```

But when booking is cancelled (user):
**File:** `app/Services/BookingService.php` | Line 115

```php
$booking->update(['status_id' => BookingStatus::cancelled()]);
$booking->timeSlots()->detach();  // ✅ Slots released
```

**Inconsistency:** Same end state (booking cancelled) but different slot handling

---

## PERFORMANCE CONCERNS

### 1. 🟡 **N+1 Query Problem in Booking List**

**File:** `app/Http/Controllers\API\V1\User\BookingController.php` | Line 20-30

```php
public function index()
{
    $bookings = Booking::with(['court', 'timeSlots', 'status'])  // ✅ Eager
        ->where('user_id', $user->id)
        ->latest()
        ->paginate(10);

    return $this->success(['data' => $bookings->items()]);
}
```

✅ **Good:** Using eager loading

But if controller iterates over `$bookings->items()` calling methods on nested relationships, additional queries could fire.

---

### 2. 🟡 **getBookedSlots() Called Every Availability Check**

**File:** `app/Http/Controllers\API\V1\Public\AvailabilityController.php` | Line 48

```php
$bookedSlotIds = BookingTimeSlot::getBookedSlots($court->id, $date->toDateString());
```

This query runs for EVERY availability check:

```sql
SELECT time_slot_id FROM booking_time_slots
WHERE court_id = ? AND booking_date = ?
AND booking_id IN (
    SELECT id FROM bookings
    WHERE status_id IN (1, 2)  -- pending, confirmed
    AND (expires_at IS NULL OR expires_at > now())
);
```

If 50 users check availability simultaneously → 50 identical queries

**Better:** Cache result with short TTL (5-10 minutes)

---

### 3. 🟡 **Missing Pagination Defaults**

**File:** `app/Http/Controllers\API\V1\User\NotificationController.php` | Line 27

```php
$notifications = $query
    ->latest()
    ->paginate($request->get('per_page', 10));  // Default 10 ✓
```

But some endpoints don't specify `per_page`:
**File:** `app/Http/Controllers\API\V1\Public\ReviewController.php` (assumed)

Could load entire result set if not cautious.

---

### 4. 🟡 **Chunking in ExpireBookings Command**

**File:** `app/Console/Commands/ExpireBookings.php` | Line 19

```php
Booking::where('status_id', BookingStatus::pending())
    ->where('expires_at', '<=', now())
    ->chunkById(50, function ($bookings) {
        foreach ($bookings as $booking) {
            // Process...
        }
    });
```

✅ **Good:** Chunks prevent memory overflow

But could be optimized to batch update instead:

```php
Booking::where('status_id', BookingStatus::pending())
    ->where('expires_at', '<=', now())
    ->update(['status_id' => BookingStatus::expired()]);

// Then detach slots...
```

---

### 5. 🟡 **No Indexes on Availability Queries**

**File:** `database/migrations/2026_03_18_055241_create_booking_time_slots_table.php`

```php
$table->index(['court_id', 'booking_date']);  // ✓ Has this
```

But `booking_time_slots` query also checks `booking.status_id` and `booking.expires_at`:

```sql
WHERE booking_id IN (
    SELECT id FROM bookings
    WHERE status_id IN (1, 2)  -- ✗ No index on status_id
    AND expires_at > now()      -- ✗ No index
);
```

**Fix:**

```php
$table->index(['status_id']);
$table->index(['expires_at']);
```

---

## MISSING TEST COVERAGE

### Current Test Files

**File:** `tests/Feature/ExampleTest.php`

- Only tests `GET /` route
- Not relevant to booking system

**File:** `tests/Unit/ExampleTest.php`

- Tests `assertTrue(true)`
- Placeholder only

### Critical Test Gaps

#### ❌ Booking Tests Needed

1. **Test double-booking prevention**

```php
public function test_cannot_book_already_booked_slot()
{
    $booking1 = Booking::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/v1/bookings', [
            'court_id' => $booking1->court_id,
            'booking_date' => $booking1->booking_date,
            'slot_ids' => $booking1->timeSlots->pluck('id')->toArray(),
        ]);

    $response->assertStatus(400);
}
```

2. **Test booking expiry**

```php
public function test_booking_expires_after_10_minutes()
{
    $booking = Booking::factory()->create();
    $this->travel(11)->minutes();

    $this->assertTrue($booking->isExpired());
}
```

3. **Test concurrent bookings**

```php
public function test_concurrent_bookings_handled_safely()
{
    // Simulate race condition
}
```

4. **Test promo application**

```php
public function test_promo_discount_applied_correctly()
{
    $promo = Promo::factory()->percentage(10)->create();

    $booking = Booking::factory()->create([
        'promo_code' => $promo->promo_code
    ]);

    // Assert price reduced by 10%
}
```

#### ❌ Payment Tests Needed

1. **Test payment confirmation**

```php
public function test_payment_confirmation_updates_booking_status()
{
    $payment = Payment::factory()->pending()->create();

    $this->actingAs($payment->booking->user)
        ->post("/api/v1/payments/{$payment->id}/confirm");

    $this->assertTrue($payment->isPaid());
}
```

2. **Test payment authorization**

```php
public function test_user_cannot_pay_others_booking()
{
    $payment = Payment::factory()->create();

    $response = $this->actingAs($otherUser)
        ->post("/api/v1/payments/{$payment->id}/confirm");

    $response->assertStatus(403);  // Forbidden
}
```

#### ❌ Availability Tests Needed

1. **Test availability respects operating hours**

```php
public function test_availability_respects_operating_hours()
{
    $hour9pm = TimeSlot::factory()->time('21:00', '22:00')->create();

    $response = $this->get('/api/v1/availability', [
        'court_id' => $court->id,
        'date' => Carbon::now()->toDateString(),
    ]);

    $this->assertFalse(
        $response->json('slots.*.id')
            ->contains($hour9pm->id)
    );
}
```

2. **Test maintenance blocks availability**
3. **Test past times unavailable**

#### ❌ Authorization Tests Needed

1. **Test admin-only endpoints blocked for users**
2. **Test owner cannot approve other owner's bookings**
3. **Test user cannot cancel past bookings**

#### ❌ Database Integrity Tests

1. **Test unique constraint on booking_time_slots**
2. **Test cascade delete on user deletion**
3. **Test payment linked correctly to booking**

---

## SUMMARY TABLE

| Category        | Issue                                        | Severity    | File                                                            | Line    |
| --------------- | -------------------------------------------- | ----------- | --------------------------------------------------------------- | ------- |
| **Auth**        | StorePaymentRequest::authorize() = false     | 🔴 CRITICAL | `app/Http/Requests/StorePaymentRequest.php`                     | 14      |
| **Logic**       | Missing BookingService::reject()             | 🔴 CRITICAL | `app/Services/BookingService.php`                               | -       |
| **Logic**       | PaymentStatus::cencelled() typo              | 🔴 CRITICAL | `app/Models/PaymentStatus.php`                                  | 36      |
| **Concurrency** | Race condition in availability check         | 🟡 HIGH     | `app/Http/Controllers\API\V1\Public\AvailabilityController.php` | 49      |
| **Scheduler**   | ExpireBookings not scheduled                 | 🟡 HIGH     | `app/Console/Commands/ExpireBookings.php`                       | -       |
| **Duplicates**  | Redundant booking update in payment confirm  | 🟡 MEDIUM   | `app/Models/Payment.php` + `PaymentController.php`              | 60 + 89 |
| **Data**        | Promo over-usage race condition              | 🟡 MEDIUM   | `app/Services/BookingService.php`                               | 63      |
| **Consistency** | Slot release inconsistent for payment cancel | 🟡 MEDIUM   | `app/Http/Controllers\API\V1\User\PaymentController.php`        | 138     |
| **Event**       | PaymentExpired not in listener map           | 🟡 MEDIUM   | `app/Providers/EventServiceProvider.php`                        | 31-71   |
| **Performance** | No caching for availability queries          | 🟡 MEDIUM   | `app/Http/Controllers\API\V1\Public\AvailabilityController.php` | 48      |
| **Timezone**    | No explicit timezone handling                | 🟡 MEDIUM   | Throughout                                                      | -       |
| **Testing**     | No meaningful tests                          | 🟡 MEDIUM   | `tests/`                                                        | -       |

---

## RECOMMENDATIONS (Priority Order)

### Phase 1: Fix Critical Bugs (IMMEDIATE)

1. Fix `StorePaymentRequest::authorize()` to return proper check
2. Add `BookingService::reject()` method
3. Fix `PaymentStatus::cencelled()` typo
4. Register `PaymentExpired` event in EventServiceProvider

### Phase 2: Fix High-Priority Issues (THIS WEEK)

1. Schedule `ExpireBookings` command in `app/Console/Kernel.php`
2. Fix race condition in AvailabilityController with transactions
3. Fix promo over-usage with atomic increment
4. Ensure slot release consistency in payment cancellation

### Phase 3: Improve Data Integrity (THIS MONTH)

1. Add missing database indexes
2. Implement comprehensive test suite (minimum 50 tests)
3. Explicit timezone configuration
4. Fix lockForUpdate() reassignment in BookingService

### Phase 4: Optimize Performance (NEXT QUARTER)

1. Implement availability result caching
2. Add database indexes for slow queries
3. Batch operations where possible
4. Consider read replicas for reporting

---

**End of Analysis**
