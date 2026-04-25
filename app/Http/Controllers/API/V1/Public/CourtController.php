<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Public\CourtResource; // Import Resource-nya
use Illuminate\Http\Request;
use App\Models\Court;

class CourtController extends Controller
{

// LIST COURT (PUBLIC)
    public function index(Request $request)
    {
        try {
            $query = Court::with(['venue', 'sport', 'images'])
                ->withAvg('reviews', 'rating') // Ambil rata-rata rating tanpa query berulang
                ->where('status', 'active');

            if ($request->filled('venue_id')) {
                $query->where('venue_id', $request->venue_id);
            }

            if ($request->filled('sport_id')) {
                $query->where('sport_id', $request->sport_id);
            }

            $courts = $query->latest()->get();

            return $this->success(
                CourtResource::collection($courts),
                'List court berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data court', $e->getMessage(), 500);
        }
    }

    // SHOW COURT DETAIL (PUBLIC)

    public function show(string $id)
    {
        try {
            $court = Court::with(['venue', 'sport', 'images', 'maintenances'])
                ->withAvg('reviews', 'rating')
                ->find($id);

            if (!$court) {
                return $this->notFound('Court tidak ditemukan');
            }

            return $this->success(
                new CourtResource($court),
                'Detail court berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail court', $e->getMessage(), 500);
        }
    }
}
