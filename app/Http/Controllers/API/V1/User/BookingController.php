<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BookingController extends Controller
{
    use AuthorizesRequests;

    protected BookingService $bookingService;

    // 🔥 Dependency Injection (WAJIB)
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
            // 1. Jalankan logic booking di Service
            $booking = $this->bookingService->store($request->validated());

            // 2. Load relasi supaya data di JSON lengkap (Opsional tapi bagus buat Test)
            $booking->load(['court', 'timeSlots', 'status']);

            // 3. Return data model langsung
            return $this->success([
                'booking' => $booking->load(['court', 'timeSlots', 'status']),
            ], 'Booking created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, 400);
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
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 400);
        }
    }
}
