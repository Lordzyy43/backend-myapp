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

class BookingController extends Controller
{
  use AuthorizesRequests;

  protected $bookingService;

  // Gunakan Dependency Injection agar lebih testable
  public function __construct(BookingService $bookingService)
  {
    $this->bookingService = $bookingService;
  }

  /**
   * CORE LOGIC: Handle Action (Approve/Reject/Finish)
   * Kita buat lebih detail dengan Database Transaction & Specific Logging
   */
  protected function handleAction($id, string $method, string $successMessage): JsonResponse
  {
    // Gunakan lockForUpdate jika aplikasi skala besar untuk cegah race condition
    $booking = Booking::with(['user', 'court', 'timeSlots', 'status'])
      ->findOrFail($id);

    // Authorization via Policy (e.g., BookingPolicy)
    $this->authorize($method, $booking);

    return DB::transaction(function () use ($booking, $method, $successMessage) {
      try {
        // Eksekusi logic di Service
        $result = $this->bookingService->{$method}($booking);

        Log::info("Booking Action Successful", [
          'action'    => $method,
          'booking_id' => $booking->id,
          'admin_id'  => auth()->id(),
          'timestamp' => now()->toDateTimeString()
        ]);

        return $this->success($result, $successMessage);
      } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Booking Action Failed", [
          'action'    => $method,
          'booking_id' => $booking->id,
          'error'     => $e->getMessage(),
          'trace'     => $e->getTraceAsString()
        ]);

        return $this->error("Gagal melakukan aksi {$method}: " . $e->getMessage(), null, 422);
      }
    });
  }

  public function index()
  {
    $bookings = \App\Models\Booking::with(['user', 'court', 'status'])->get();
    return response()->json([
      'success' => true,
      'data' => ['bookings' => $bookings]
    ]);
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
   * REPORT & MONITORING
   * Ditambahkan filtering date range dan sorting
   */
  public function report(Request $request): JsonResponse
  {
    $this->authorize('viewAny', Booking::class);

    $query = Booking::with(['user:id,name,email', 'court:id,name', 'status:id,name']);

    // Filter Berdasarkan Status
    $query->when($request->status_id, function ($q) use ($request) {
      $q->where('status_id', $request->status_id);
    });

    // Filter Rentang Tanggal (Start & End Date)
    $query->when($request->start_date && $request->end_date, function ($q) use ($request) {
      $q->whereBetween('booking_date', [$request->start_date, $request->end_date]);
    }, function ($q) use ($request) {
      // Default filter single date jika start/end tidak ada
      $q->when($request->date, fn($query) => $query->whereDate('booking_date', $request->date));
    });

    $bookings = $query->latest()->paginate($request->get('per_page', 10));

    return $this->success([
      'data' => $bookings->items(),
      'pagination' => [
        'total'        => $bookings->total(),
        'per_page'     => $bookings->perPage(),
        'current_page' => $bookings->currentPage(),
        'last_page'    => $bookings->lastPage(),
      ]
    ], 'Data laporan booking berhasil ditarik');
  }

  /**
   * ADVANCED USER MANAGEMENT
   * Search lebih powerful (nama, email, role)
   */
  public function usersIndex(Request $request): JsonResponse
  {
    $this->authorize('viewAny', User::class);

    $query = User::with(['role:id,role_name']);

    // Search multikolom
    $query->when($request->search, function ($q) use ($request) {
      $search = $request->search;
      $q->where(function ($sub) use ($search) {
        $sub->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    });

    // Filter Role
    $query->when($request->role, function ($q) use ($request) {
      $q->whereHas('role', fn($sub) => $sub->where('role_name', $request->role));
    });

    $users = $query->latest()->paginate($request->get('per_page', 10));

    return $this->success([
      'data' => $users->items(),
      'pagination' => [
        'total'        => $users->total(),
        'per_page'     => $users->perPage(),
        'current_page' => $users->currentPage(),
        'last_page'    => $users->lastPage(),
      ]
    ], 'Daftar pengguna berhasil dimuat');
  }
}
