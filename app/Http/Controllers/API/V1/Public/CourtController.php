<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Court;

class CourtController extends Controller
{
    /**
     * LIST COURT (PUBLIC)
     */
    public function index(Request $request)
    {
        try {
            $query = Court::with(['venue', 'sport'])
                ->where('status', 'active');

            if ($request->filled('venue_id')) {
                $query->where('venue_id', $request->venue_id);
            }

            $courts = $query->latest()->get();

            return $this->success($courts, 'List court berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data court', $e->getMessage(), 500);
        }
    }

    /**
     * DETAIL COURT (PUBLIC)
     */
    public function show(string $id)
    {
        try {
            $court = Court::with(['venue', 'sport', 'images'])
                ->find($id);

            if (!$court) {
                return $this->notFound('Court tidak ditemukan');
            }

            return $this->success($court, 'Detail court berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail court', $e->getMessage(), 500);
        }
    }
}
