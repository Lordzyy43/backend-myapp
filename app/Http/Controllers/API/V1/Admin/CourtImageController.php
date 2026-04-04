<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CourtImage;
use App\Models\Court;
use Illuminate\Validation\ValidationException;

class CourtImageController extends Controller
{
    /**
     * LIST images by court
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

            return $this->success($images, 'List gambar court berhasil diambil');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil gambar court', $e->getMessage(), 500);
        }
    }

    /**
     * UPLOAD image
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'court_id' => 'required|exists:courts,id',
                'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $court = Court::with('venue')->findOrFail($validated['court_id']);
            $user = auth()->user();

            // 🔒 Ownership check
            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return $this->error('Bukan court milik anda', null, 403);
            }

            $path = $request->file('image')->store('court_images', 'public');

            $image = CourtImage::create([
                'court_id' => $court->id,
                'image_url' => $path,
            ]);

            return $this->success($image, 'Gambar court berhasil diupload', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal upload gambar court', $e->getMessage(), 500);
        }
    }

    /**
     * DELETE image
     */
    public function destroy(string $id)
    {
        try {
            $image = CourtImage::with('court.venue')->findOrFail($id);
            $user = auth()->user();

            // 🔒 Ownership check
            if ($image->court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return $this->error('Forbidden', null, 403);
            }

            $rawPath = $image->getRawPath();
            if ($rawPath && Storage::disk('public')->exists($rawPath)) {
                Storage::disk('public')->delete($rawPath);
            }

            $image->delete();

            return $this->success(null, 'Gambar court berhasil dihapus');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Gambar tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus gambar', $e->getMessage(), 500);
        }
    }
}
