<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\VenueImage;
use App\Models\Venue;

class VenueImageController extends Controller
{
    /**
     * GET images by venue
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'venue_id' => 'required|exists:venues,id',
            ]);

            $images = VenueImage::where('venue_id', $request->venue_id)
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $images
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil gambar',
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
                'venue_id' => 'required|exists:venues,id',
                'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $venue = Venue::findOrFail($request->venue_id);

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Bukan venue milik anda'
                ], 403);
            }

            // 🔥 simpan file
            $path = $request->file('image')->store('venue_images', 'public');

            $image = VenueImage::create([
                'venue_id' => $venue->id,
                'image_url' => $path,
            ]);

            return response()->json([
                'message' => 'Gambar berhasil diupload',
                'data' => $image
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal upload gambar',
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

            $image = VenueImage::findOrFail($id);
            $venue = $image->venue;

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            // 🔥 hapus file dari storage
            $rawPath = $image->getRawPath();

            if ($rawPath && Storage::disk('public')->exists($rawPath)) {
                Storage::disk('public')->delete($rawPath);
            }

            $image->delete();

            return response()->json([
                'message' => 'Gambar berhasil dihapus'
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
