<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CourtImage;
use App\Models\Court;

class CourtImageController extends Controller
{
    /**
     * GET images by court
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'court_id' => 'required|exists:courts,id',
            ]);

            $images = CourtImage::where('court_id', $request->court_id)
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $images
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil gambar court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPLOAD image
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
                'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $court = Court::with('venue')->findOrFail($request->court_id);

            // 🔥 ownership check (via venue)
            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Bukan court milik anda'
                ], 403);
            }

            // 🔥 simpan file
            $path = $request->file('image')->store('court_images', 'public');

            $image = CourtImage::create([
                'court_id' => $court->id,
                'image_url' => $path,
            ]);

            return response()->json([
                'message' => 'Gambar court berhasil diupload',
                'data' => $image
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal upload gambar court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE image
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

            $image = CourtImage::with('court.venue')->findOrFail($id);

            // 🔥 ownership check
            if ($image->court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            // 🔥 delete file
            $rawPath = $image->getRawPath();

            if ($rawPath && Storage::disk('public')->exists($rawPath)) {
                Storage::disk('public')->delete($rawPath);
            }

            $image->delete();

            return response()->json([
                'message' => 'Gambar court berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Gambar tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus gambar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
