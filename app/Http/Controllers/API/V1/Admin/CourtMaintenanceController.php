<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtMaintenance;
use App\Models\Court;
use Illuminate\Validation\ValidationException;

class CourtMaintenanceController extends Controller
{
    /**
     * LIST maintenance by court
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

            return $this->success($maintenances, 'List maintenance berhasil diambil');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data maintenance', $e->getMessage(), 500);
        }
    }

    /**
     * CREATE maintenance
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'court_id' => 'required|exists:courts,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $court = Court::with('venue')->findOrFail($validated['court_id']);
            $user = auth()->user();

            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return $this->error('Bukan court milik anda', null, 403);
            }

            // 🔥 VALIDASI OVERLAP
            $overlap = CourtMaintenance::where('court_id', $court->id)
                ->where(function ($q) use ($validated) {
                    $q->where('start_date', '<=', $validated['end_date'])
                        ->where('end_date', '>=', $validated['start_date']);
                })->exists();

            if ($overlap) {
                return $this->error('Jadwal maintenance overlap dengan yang sudah ada', null, 409);
            }

            $maintenance = CourtMaintenance::create($validated);

            return $this->success($maintenance, 'Maintenance berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat maintenance', $e->getMessage(), 500);
        }
    }

    /**
     * UPDATE maintenance
     */
    public function update(Request $request, string $id)
    {
        try {
            $maintenance = CourtMaintenance::with('court.venue')->findOrFail($id);
            $user = auth()->user();

            if ($maintenance->court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return $this->error('Forbidden', null, 403);
            }

            $validated = $request->validate([
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
            ]);

            $start = $validated['start_date'] ?? $maintenance->start_date;
            $end = $validated['end_date'] ?? $maintenance->end_date;

            // 🔥 VALIDASI OVERLAP (exclude diri sendiri)
            $overlap = CourtMaintenance::where('court_id', $maintenance->court_id)
                ->where('id', '!=', $maintenance->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $end)
                        ->where('end_date', '>=', $start);
                })->exists();

            if ($overlap) {
                return $this->error('Jadwal maintenance overlap', null, 409);
            }

            $maintenance->update([
                'start_date' => $start,
                'end_date' => $end,
                'reason' => $validated['reason'] ?? $maintenance->reason,
            ]);

            return $this->success($maintenance, 'Maintenance berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Data tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal update maintenance', $e->getMessage(), 500);
        }
    }

    /**
     * DELETE maintenance
     */
    public function destroy(string $id)
    {
        try {
            $maintenance = CourtMaintenance::with('court.venue')->findOrFail($id);
            $user = auth()->user();

            if ($maintenance->court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return $this->error('Forbidden', null, 403);
            }

            $maintenance->delete();

            return $this->success(null, 'Maintenance berhasil dihapus');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Data tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus maintenance', $e->getMessage(), 500);
        }
    }
}
