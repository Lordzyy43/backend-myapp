<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CourtMaintenance;
use App\Models\Court;
use Carbon\Carbon;

class CourtMaintenanceController extends Controller
{
    /**
     * GET maintenance by court
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'court_id' => 'required|exists:courts,id',
            ]);

            $maintenances = CourtMaintenance::where('court_id', $request->court_id)
                ->orderBy('start_date')
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $maintenances
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data maintenance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CREATE maintenance
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isOwner() && !$user->isAdmin())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'court_id' => 'required|exists:courts,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $court = Court::findOrFail($request->court_id);

            // 🔥 ownership check
            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Bukan court milik anda'
                ], 403);
            }

            // 🔥 VALIDASI OVERLAP (INI KRITIS)
            $overlap = CourtMaintenance::where('court_id', $request->court_id)
                ->where(function ($q) use ($request) {
                    $q->where('start_date', '<=', $request->end_date)
                        ->where('end_date', '>=', $request->start_date);
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Jadwal maintenance overlap dengan yang sudah ada'
                ], 409);
            }

            $maintenance = CourtMaintenance::create([
                'court_id' => $request->court_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Maintenance berhasil dibuat',
                'data' => $maintenance
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal membuat maintenance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE maintenance
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isOwner() && !$user->isAdmin())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $maintenance = CourtMaintenance::findOrFail($id);
            $court = $maintenance->court;

            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $start = $request->start_date ?? $maintenance->start_date;
            $end = $request->end_date ?? $maintenance->end_date;

            // 🔥 VALIDASI OVERLAP (exclude diri sendiri)
            $overlap = CourtMaintenance::where('court_id', $court->id)
                ->where('id', '!=', $maintenance->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $end)
                        ->where('end_date', '>=', $start);
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'Jadwal maintenance overlap'
                ], 409);
            }

            $maintenance->update([
                'start_date' => $start,
                'end_date' => $end,
                'reason' => $request->reason ?? $maintenance->reason,
            ]);

            return response()->json([
                'message' => 'Maintenance berhasil diupdate',
                'data' => $maintenance
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal update maintenance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE maintenance
     */
    public function destroy(string $id)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isOwner() && !$user->isAdmin())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $maintenance = CourtMaintenance::findOrFail($id);

            if ($maintenance->court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $maintenance->delete();

            return response()->json([
                'message' => 'Maintenance berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus maintenance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
