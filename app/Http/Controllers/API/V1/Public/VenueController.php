<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Http\Resources\V1\Public\VenueResource;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    /**
     * LIST VENUE (PUBLIC)
     */
    public function index()
    {
        try {
            $venues = Venue::with(['images' => fn($q) => $q->where('is_primary', true)])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->latest()
                ->get();

            // Pakai ::collection untuk data banyak
            return $this->success(
                VenueResource::collection($venues),
                'List venue berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data venue', $e->getMessage(), 500);
        }
    }


    // SHOW VENUE DETAIL (PUBLIC)
    public function show(string $id)
    {
        try {
            $venue = Venue::with(['courts', 'images', 'operatingHours'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->find($id);

            if (!$venue) {
                return $this->notFound('Venue tidak ditemukan');
            }

            // Pakai 'new' untuk data tunggal
            return $this->success(
                new VenueResource($venue),
                'Detail venue berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail venue', $e->getMessage(), 500);
        }
    }
}
