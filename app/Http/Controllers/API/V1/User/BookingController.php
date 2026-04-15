<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BookingController extends Controller
{
    use AuthorizesRequests;

    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * LIST BOOKING (USER)
     */
    public function index()
    {
        $user = auth()->user();

        $bookings = Booking::with(['court', 'timeSlots', 'status'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return $this->success([
            'bookings' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ], 'List booking berhasil diambil');
    }

    /**
     * STORE BOOKING
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->store($request->validated());

            // 🔥 Cache invalidation (availability)
            $dateStr = Carbon::parse($request->booking_date)->toDateString();
            $cacheKey = "availability_{$request->court_id}_{$dateStr}";
            Cache::forget($cacheKey);

            return $this->success([
                'booking' => $booking->load(['court', 'timeSlots', 'status']),
            ], 'Booking created successfully', 201);
        } catch (ValidationException $e) {

            $message = collect($e->errors())->flatten()->first();

            /**
             * 🔥 RULE PENTING:
             * - Slot sudah dibooking → 400 (sesuai test concurrency)
             * - Promo error → 422
             * - Default validation → 422
             */
            if ($message === 'Slot sudah dibooking') {
                return $this->error($message, $e->errors(), 400);
            }

            return $this->error($message, $e->errors(), 422);
        } catch (\Exception $e) {

            return $this->error($e->getMessage(), null, 422);
        }
    }

    /**
     * SHOW BOOKING
     */
    public function show(string $id)
    {
        $booking = Booking::with(['court', 'timeSlots', 'status'])
            ->findOrFail($id);

        $this->authorize('view', $booking);

        return $this->success($booking, 'Detail booking berhasil diambil');
    }

    /**
     * CANCEL BOOKING
     */
    public function cancel(string $id)
    {
        $booking = Booking::findOrFail($id);

        $this->authorize('cancel', $booking);

        try {
            $this->bookingService->cancel($booking);

            return $this->success(null, 'Booking berhasil dibatalkan');
        } catch (ValidationException $e) {

            return $this->error(
                collect($e->errors())->flatten()->first(),
                $e->errors(),
                422
            );
        } catch (\Throwable $e) {

            return $this->error($e->getMessage(), null, 400);
        }
    }
}
