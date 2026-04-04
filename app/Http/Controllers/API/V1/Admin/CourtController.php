<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Validation\ValidationException;

class CourtController extends Controller
{
    /**
     * CREATE COURT
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'venue_id' => 'required|exists:venues,id',
                'sport_id' => 'required|exists:sports,id',
                'name' => 'required|string|max:255',
                'price_per_hour' => 'required|numeric|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $slug = Str::slug($validated['name']);

            $count = Court::where('venue_id', $validated['venue_id'])
                ->where('slug', 'like', "$slug%")
                ->count();

            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            $court = Court::create([
                'venue_id' => $validated['venue_id'],
                'sport_id' => $validated['sport_id'],
                'name' => $validated['name'],
                'price_per_hour' => $validated['price_per_hour'],
                'status' => $validated['status'],
                'slug' => $slug,
            ]);

            return $this->success($court, 'Court berhasil dibuat', 201);
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal membuat court', $e->getMessage(), 500);
        }
    }

    /**
     * UPDATE COURT
     */
    public function update(Request $request, string $id)
    {
        try {
            $court = Court::find($id);

            if (!$court) {
                return $this->notFound('Court tidak ditemukan');
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'price_per_hour' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:active,inactive',
                'sport_id' => 'sometimes|exists:sports,id',
            ]);

            if (isset($validated['name'])) {
                $slug = Str::slug($validated['name']);

                $count = Court::where('venue_id', $court->venue_id)
                    ->where('slug', 'like', "$slug%")
                    ->where('id', '!=', $court->id)
                    ->count();

                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }

                $court->slug = $slug;
            }

            $court->update($validated);

            return $this->success($court, 'Court berhasil diupdate');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal update court', $e->getMessage(), 500);
        }
    }

    /**
     * DELETE COURT
     */
    public function destroy(string $id)
    {
        try {
            $court = Court::find($id);

            if (!$court) {
                return $this->notFound('Court tidak ditemukan');
            }

            $court->delete();

            return $this->success(null, 'Court berhasil dihapus');
        } catch (\Exception $e) {
            return $this->error('Gagal menghapus court', $e->getMessage(), 500);
        }
    }
}
