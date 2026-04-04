<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
  /**
   * Helper biar gak repeat logic
   */
  protected function handleAction($id, string $method, string $successMessage)
  {
    $booking = Booking::with(['user', 'court', 'timeSlots', 'status'])
      ->findOrFail($id);

    // 🔥 Authorization (WAJIB untuk clean architecture)
    $this->authorize($method, $booking);

    try {
      $service = new BookingService();
      $result = $service->{$method}($booking);

      Log::info("Booking {$method} by admin", [
        'booking_id' => $booking->id,
        'admin_id' => auth()->id(),
      ]);

      return $this->success($result, $successMessage);
    } catch (\Exception $e) {
      Log::error("Booking {$method} failed", [
        'booking_id' => $booking->id,
        'error' => $e->getMessage(),
      ]);

      return $this->error($e->getMessage(), null, 400);
    }
  }

  /**
   * APPROVE
   */
  public function approve($id)
  {
    return $this->handleAction($id, 'approve', 'Booking berhasil di-approve');
  }

  /**
   * REJECT
   */
  public function reject($id)
  {
    return $this->handleAction($id, 'reject', 'Booking berhasil ditolak');
  }

  /**
   * FINISH
   */
  public function finish($id)
  {
    return $this->handleAction($id, 'finish', 'Booking berhasil diselesaikan');
  }

  /**
   * REPORT BOOKING
   */
  public function report(Request $request)
  {
    $query = Booking::with(['user', 'court', 'status']);

    if ($request->filled('status_id')) {
      $query->where('status_id', $request->status_id);
    }

    if ($request->filled('date')) {
      $query->whereDate('booking_date', $request->date);
    }

    $bookings = $query->latest()->paginate(10);

    return $this->success([
      'bookings' => $bookings->items(),
      'meta' => [
        'current_page' => $bookings->currentPage(),
        'last_page' => $bookings->lastPage(),
        'per_page' => $bookings->perPage(),
        'total' => $bookings->total(),
      ]
    ], 'Report booking berhasil diambil');
  }

  /**
   * USER MANAGEMENT
   */
  public function usersIndex(Request $request)
  {
    $query = \App\Models\User::with(['role']);

    if ($request->filled('role')) {
      $query->whereHas('role', function ($q) use ($request) {
        $q->where('role_name', $request->role);
      });
    }

    if ($request->filled('search')) {
      $query->where(function ($q) use ($request) {
        $q->where('name', 'like', '%' . $request->search . '%')
          ->orWhere('email', 'like', '%' . $request->search . '%');
      });
    }

    $users = $query->latest()->paginate(10);

    return $this->success([
      'data' => $users->items(),
      'meta' => [
        'current_page' => $users->currentPage(),
        'last_page' => $users->lastPage(),
        'per_page' => $users->perPage(),
        'total' => $users->total(),
      ]
    ], 'Users berhasil diambil');
  }
}
