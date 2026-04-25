<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Public\TimeSlotResource; // Import Resource
use App\Models\TimeSlot;

class TimeSlotController extends Controller
{

    // Public API untuk mendapatkan daftar time slot yang aktif
    public function index()
    {
        try {
            // Kita ambil slot yang aktif dan urutkan berdasarkan waktu mulai
            $slots = TimeSlot::where('is_active', true)
                ->orderBy('order_index')
                ->orderBy('start_time')
                ->get();

            return $this->success(
                TimeSlotResource::collection($slots),
                'List time slot berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil time slots', $e->getMessage(), 500);
        }
    }

    // Public API untuk mendapatkan detail time slot berdasarkan ID

    public function show(string $id)
    {
        try {
            $slot = TimeSlot::find($id);

            if (!$slot) {
                return $this->notFound('Time slot tidak ditemukan');
            }

            return $this->success(
                new TimeSlotResource($slot),
                'Detail time slot berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data slot', $e->getMessage(), 500);
        }
    }
}
