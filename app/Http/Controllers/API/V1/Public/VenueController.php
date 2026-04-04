<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Venue;

class VenueController extends Controller
{
    /**
     * LIST VENUE (PUBLIC)
     */
    public function index()
    {
        try {
            $venues = Venue::with(['courts'])
                ->latest()
                ->get();

            return $this->success($venues, 'List venue berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data venue', $e->getMessage(), 500);
        }
    }

    /**
     * DETAIL VENUE (PUBLIC)
     */
    public function show(string $id)
    {
        try {
            $venue = Venue::with(['courts', 'images', 'operatingHours'])
                ->find($id);

            if (!$venue) {
                return $this->notFound('Venue tidak ditemukan');
            }

            return $this->success($venue, 'Detail venue berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail venue', $e->getMessage(), 500);
        }
    }
}
