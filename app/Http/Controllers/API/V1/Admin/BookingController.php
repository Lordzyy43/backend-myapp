<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
  use AuthorizesRequests;

  protected $bookingService;

  public function __construct(BookingService $bookingService)
  {
    $this->bookingService = $bookingService;
  }

  /**
   * CORE ACTION HANDLER
   */
  protected function handleAction($id, string $method, string $successMessage): JsonResponse
  {
    $booking = Booking::with(['user', 'court', 'timeSlots', 'status'])->findOrFail($id);

    $this->authorize($method, $booking);

    try {
      // 🔥 VALIDASI STATE (HARUS VALIDATION EXCEPTION)
      $this->validateBookingState($booking, $method);

      return DB::transaction(function () use ($booking, $method, $successMessage) {

        $result = $this->bookingService->{$method}($booking);

        Log::info("Booking Action Successful", [
          'action' => $method,
          'booking_id' => $booking->id,
          'admin_id' => auth()->id(),
        ]);

        return $this->success($result, $successMessage);
      });
    } catch (ValidationException $e) {

      return $this->error(
        collect($e->errors())->flatten()->first(),
        $e->errors(),
        422
      );
    } catch (\Exception $e) {

      Log::error("Booking Action Failed [{$method}] ID: {$booking->id}", [
        'error' => $e->getMessage()
      ]);

      return $this->error($e->getMessage(), null, 422);
    }
  }

  /**
   * 🔥 VALIDATION GUARD (CRITICAL FIX)
   */
  protected function validateBookingState(Booking $booking, string $method)
  {
    $status = $booking->status->status_name;

    switch ($method) {
      case 'approve':
        // 🔥 STANDAR INDUSTRI: Idempotency
        // Jika status sudah 'confirmed', jangan lempar error (biarkan lolos ke Service).
        if ($status === 'confirmed') {
          return;
        }

        // Tetap blokir jika status selain pending (misal: sudah rejected atau cancelled)
        if ($status !== 'pending') {
          throw ValidationException::withMessages([
            'booking' => ['Booking status cannot be changed']
          ]);
        }
        break;

      case 'reject':
        // Idempotensi untuk reject juga (opsional tapi disarankan)
        if ($status === 'cancelled') return;

        if ($status !== 'pending') {
          throw ValidationException::withMessages([
            'booking' => ['Booking status cannot be changed']
          ]);
        }
        break;

      case 'finish':
        // Idempotensi untuk finish
        if ($status === 'finished') return;

        if ($status !== 'confirmed') {
          throw ValidationException::withMessages([
            'booking' => ['Booking status cannot be changed']
          ]);
        }
        break;
    }
  }

  /**
   * LIST BOOKING
   */
  public function index()
  {
    $bookings = Booking::with(['user', 'court', 'status'])->get();

    return $this->success([
      'bookings' => $bookings
    ], 'List booking berhasil diambil');
  }

  public function approve($id)
  {
    return $this->handleAction($id, 'approve', 'Booking berhasil di-approve');
  }

  public function reject($id)
  {
    return $this->handleAction($id, 'reject', 'Booking berhasil di-reject');
  }

  public function finish($id)
  {
    return $this->handleAction($id, 'finish', 'Booking berhasil di-selesaikan');
  }

  /**
   * REPORT
   */
  public function report(Request $request): JsonResponse
  {
    $this->authorize('viewAny', Booking::class);

    $query = Booking::with(['user:id,name,email', 'court:id,name', 'status:id,name']);

    $query->when($request->status_id, function ($q) use ($request) {
      $q->where('status_id', $request->status_id);
    });

    $query->when($request->start_date && $request->end_date, function ($q) use ($request) {
      $q->whereBetween('booking_date', [$request->start_date, $request->end_date]);
    }, function ($q) use ($request) {
      $q->when($request->date, fn($query) => $query->whereDate('booking_date', $request->date));
    });

    $bookings = $query->latest()->paginate($request->get('per_page', 10));

    return $this->success([
      'data' => $bookings->items(),
      'pagination' => [
        'total' => $bookings->total(),
        'per_page' => $bookings->perPage(),
        'current_page' => $bookings->currentPage(),
        'last_page' => $bookings->lastPage(),
      ]
    ], 'Data laporan booking berhasil ditarik');
  }

  /**
   * USER MANAGEMENT
   */
  public function usersIndex(Request $request): JsonResponse
  {
    $this->authorize('viewAny', User::class);

    $query = User::with(['role:id,role_name']);

    $query->when($request->search, function ($q) use ($request) {
      $search = $request->search;
      $q->where(function ($sub) use ($search) {
        $sub->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    });

    $query->when($request->role, function ($q) use ($request) {
      $q->whereHas('role', fn($sub) => $sub->where('role_name', $request->role));
    });

    $users = $query->latest()->paginate($request->get('per_page', 10));

    return $this->success([
      'data' => $users->items(),
      'pagination' => [
        'total' => $users->total(),
        'per_page' => $users->perPage(),
        'current_page' => $users->currentPage(),
        'last_page' => $users->lastPage(),
      ]
    ], 'Daftar pengguna berhasil dimuat');
  }
}
