<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeSlot;
use Illuminate\Validation\ValidationException;

class TimeSlotController extends Controller
{
    /**
     * CREATE SLOT
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'label' => 'nullable|string|max:50',
            ]);

            // 🔥 OVERLAP VALIDATION
            $overlap = TimeSlot::where(function ($q) use ($validated) {
                $q->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })->exists();

            if ($overlap) {
                return $this->error('Time slot overlap dengan slot lain', null, 409);
            }

            $lastOrder = TimeSlot::max('order_index') ?? 0;

            $slot = TimeSlot::create([
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'order_index' => $lastOrder + 1,
                'is_active' => true,
                'label' => $validated['label'] ?? ($validated['start_time'] . ' - ' . $validated['end_time']),
            ]);

            return $this->success($slot, 'Time slot berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat time slot', $e->getMessage(), 500);
        }
    }

    /**
     * UPDATE SLOT
     */
    public function update(Request $request, string $id)
    {
        try {
            $slot = TimeSlot::find($id);

            if (!$slot) {
                return $this->notFound('Time slot tidak ditemukan');
            }

            $validated = $request->validate([
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'label' => 'nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
            ]);

            $start = $validated['start_time'] ?? $slot->start_time;
            $end = $validated['end_time'] ?? $slot->end_time;

            // 🔥 OVERLAP VALIDATION (exclude diri sendiri)
            $overlap = TimeSlot::where('id', '!=', $slot->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })
                ->exists();

            if ($overlap) {
                return $this->error('Time slot overlap dengan slot lain', null, 409);
            }

            $slot->update([
                'start_time' => $start,
                'end_time' => $end,
                'label' => $validated['label'] ?? ($start . ' - ' . $end),
                'is_active' => $validated['is_active'] ?? $slot->is_active,
            ]);

            return $this->success($slot, 'Time slot berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal update time slot', $e->getMessage(), 500);
        }
    }

    /**
     * DELETE SLOT
     */
    public function destroy(string $id)
    {
        try {
            $slot = TimeSlot::find($id);

            if (!$slot) {
                return $this->notFound('Time slot tidak ditemukan');
            }

            $slot->delete();

            return $this->success(null, 'Time slot berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus slot', $e->getMessage(), 500);
        }
    }
}
