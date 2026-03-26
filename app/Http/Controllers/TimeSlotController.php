<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeSlot;
use Carbon\Carbon;

class TimeSlotController extends Controller
{
    /**
     * PUBLIC: list active time slots
     */
    public function index()
    {
        try {
            $slots = TimeSlot::active()
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $slots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil time slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ADMIN/OWNER: create slot
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isAdmin() && !$user->isOwner())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'label' => 'nullable|string|max:50',
            ]);

            // 🔥 VALIDASI OVERLAP (INI PENTING BANGET)
            $overlap = TimeSlot::where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Time slot overlap dengan slot lain'
                ], 409);
            }

            // 🔥 AUTO ORDER INDEX
            $lastOrder = TimeSlot::max('order_index') ?? 0;

            $slot = TimeSlot::create([
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'order_index' => $lastOrder + 1,
                'is_active' => true,
                'label' => $request->label ?? ($request->start_time . ' - ' . $request->end_time),
            ]);

            return response()->json([
                'message' => 'Time slot berhasil dibuat',
                'data' => $slot
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal membuat time slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUBLIC: detail slot
     */
    public function show(string $id)
    {
        try {
            $slot = TimeSlot::findOrFail($id);

            return response()->json([
                'message' => 'Success',
                'data' => $slot
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Time slot tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ADMIN/OWNER: update slot
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isAdmin() && !$user->isOwner())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $slot = TimeSlot::findOrFail($id);

            $request->validate([
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'label' => 'nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
            ]);

            $start = $request->start_time ?? $slot->start_time;
            $end = $request->end_time ?? $slot->end_time;

            // 🔥 VALIDASI OVERLAP (EXCLUDE DIRI SENDIRI)
            $overlap = TimeSlot::where('id', '!=', $slot->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Time slot overlap dengan slot lain'
                ], 409);
            }

            $slot->update([
                'start_time' => $start,
                'end_time' => $end,
                'label' => $request->label ?? ($start . ' - ' . $end),
                'is_active' => $request->is_active ?? $slot->is_active,
            ]);

            return response()->json([
                'message' => 'Time slot berhasil diupdate',
                'data' => $slot
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Time slot tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal update time slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ADMIN: delete slot
     */
    public function destroy(string $id)
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Hanya admin yang bisa menghapus slot'
                ], 403);
            }

            $slot = TimeSlot::findOrFail($id);

            $slot->delete();

            return response()->json([
                'message' => 'Time slot berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Time slot tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
