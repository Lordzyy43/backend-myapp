<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sport;
use Illuminate\Validation\ValidationException;

class SportController extends Controller
{
    /**
     * LIST all active sports
     */
    public function index()
    {
        try {
            $sports = Sport::active()->orderBy('sort_order')->get();
            return $this->success($sports, 'List sports berhasil diambil');
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil sports', $e->getMessage(), 500);
        }
    }

    /**
     * CREATE sport
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|unique:sports,name',
                'icon' => 'nullable|string',
                'image' => 'nullable|string',
            ]);

            $sport = Sport::create($validated);

            return $this->success($sport, 'Sport berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat sport', $e->getMessage(), 500);
        }
    }

    /**
     * SHOW sport by slug
     */
    public function show($slug)
    {
        try {
            $sport = Sport::where('slug', $slug)->firstOrFail();
            return $this->success($sport, 'Sport berhasil diambil');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Sport tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil sport', $e->getMessage(), 500);
        }
    }

    /**
     * UPDATE sport
     */
    public function update(Request $request, $id)
    {
        try {
            $sport = Sport::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|unique:sports,name,' . $id,
                'icon' => 'nullable|string',
                'image' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            $sport->update($validated);

            return $this->success($sport, 'Sport berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Sport tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal update sport', $e->getMessage(), 500);
        }
    }

    /**
     * DISABLE sport (soft disable)
     */
    public function destroy($id)
    {
        try {
            $sport = Sport::findOrFail($id);
            $sport->update(['is_active' => false]);

            return $this->success(null, 'Sport berhasil dinonaktifkan');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Sport tidak ditemukan', null, 404);
        } catch (\Exception $e) {
            return $this->error('Gagal menonaktifkan sport', $e->getMessage(), 500);
        }
    }
}
