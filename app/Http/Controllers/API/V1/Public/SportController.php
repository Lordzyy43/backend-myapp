<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Public\SportResource; // Import Resource-nya
use App\Models\Sport;
use Illuminate\Http\Request;

class SportController extends Controller
{
    /**
     * Get all active sports (PUBLIC)
     */
    public function index()
    {
        try {
            $sports = Sport::active()
                ->orderBy('sort_order')
                ->get();

            // Gunakan Resource agar icon_url ter-generate otomatis
            return $this->success(
                SportResource::collection($sports),
                'List cabang olahraga berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data sport', $e->getMessage(), 500);
        }
    }

    /**
     * Show by slug (PUBLIC)
     */
    public function show($slug)
    {
        try {
            $sport = Sport::active()
                ->where('slug', $slug)
                ->first();

            if (!$sport) {
                return $this->notFound('Cabang olahraga tidak ditemukan atau tidak aktif');
            }

            return $this->success(
                new SportResource($sport),
                'Detail cabang olahraga berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil detail sport', $e->getMessage(), 500);
        }
    }
}
