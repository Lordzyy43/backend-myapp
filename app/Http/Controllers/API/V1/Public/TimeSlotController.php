<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;

class TimeSlotController extends Controller
{
    /**
     * LIST SLOT (PUBLIC)
     */
    public function index()
    {
        try {
            $slots = TimeSlot::active()
                ->orderBy('start_time')
                ->get();

            return $this->success($slots, 'List time slot berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil time slots', $e->getMessage(), 500);
        }
    }

    /**
     * DETAIL SLOT (PUBLIC)
     */
    public function show(string $id)
    {
        try {
            $slot = TimeSlot::find($id);

            if (!$slot) {
                return $this->notFound('Time slot tidak ditemukan');
            }

            return $this->success($slot, 'Detail time slot berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data slot', $e->getMessage(), 500);
        }
    }
}
