<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Venue;
use Illuminate\Validation\ValidationException;

class VenueController extends Controller
{
    /**
     * CREATE VENUE
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'description' => 'nullable|string',
            ]);

            $slug = Str::slug($validated['name']);

            $count = Venue::where('slug', 'like', "$slug%")->count();
            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            $venue = Venue::create([
                'name' => $validated['name'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'description' => $validated['description'] ?? null,
                'slug' => $slug,
            ]);

            return $this->success($venue, 'Venue berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat venue', $e->getMessage(), 500);
        }
    }

    /**
     * UPDATE VENUE
     */
    public function update(Request $request, string $id)
    {
        try {
            $venue = Venue::find($id);

            if (!$venue) {
                return $this->notFound('Venue tidak ditemukan');
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:100',
                'description' => 'nullable|string',
            ]);

            if (isset($validated['name'])) {
                $slug = Str::slug($validated['name']);

                $count = Venue::where('slug', 'like', "$slug%")
                    ->where('id', '!=', $venue->id)
                    ->count();

                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }

                $venue->slug = $slug;
            }

            $venue->update($validated);

            return $this->success($venue, 'Venue berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal update venue', $e->getMessage(), 500);
        }
    }

    /**
     * DELETE VENUE
     */
    public function destroy(string $id)
    {
        try {
            $venue = Venue::find($id);

            if (!$venue) {
                return $this->notFound('Venue tidak ditemukan');
            }

            $venue->delete();

            return $this->success(null, 'Venue berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus venue', $e->getMessage(), 500);
        }
    }
}
