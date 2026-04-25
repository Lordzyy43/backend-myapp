<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Http\Requests\API\V1\User\StoreBookingRequest;
use App\Http\Resources\V1\User\BookingResource; // Tambahkan Resource
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

        // Tambahkan 'court.sport' agar Resource bisa menampilkan info olahraga
        $bookings = Booking::with(['court.sport', 'timeSlots', 'status', 'payment'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return $this->success(
            BookingResource::collection($bookings)->response()->getData(true),
            'List booking berhasil diambil'
        );
    }

    /**
     * STORE BOOKING
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->store($request->validated());

            // 🔥 Cache invalidation untuk availability
            $dateStr = Carbon::parse($request->booking_date)->toDateString();
            $cacheKey = "availability_{$request->court_id}_{$dateStr}";
            Cache::forget($cacheKey);

            return $this->success(
                new BookingResource($booking->load(['court.sport', 'timeSlots', 'status'])),
                'Booking created successfully',
                201
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            // Sesuai aturan main kamu untuk test concurrency
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
        // FindOrFail otomatis lempar 404 jika tidak ada
        $booking = Booking::with(['court.sport', 'timeSlots', 'status', 'payment'])
            ->findOrFail($id);

        $this->authorize('view', $booking);

        return $this->success(
            new BookingResource($booking),
            'Detail booking berhasil diambil'
        );
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
