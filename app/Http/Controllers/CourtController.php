<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Court;
use App\Models\Venue;

class CourtController extends Controller
{
    /**
     * PUBLIC: list court (optional filter by venue)
     */
    public function index(Request $request)
    {
        try {
            $query = Court::with(['venue', 'sport'])
                ->where('status', 'active');

            if ($request->venue_id) {
                $query->where('venue_id', $request->venue_id);
            }

            $courts = $query->latest()->get();

            return response()->json([
                'message' => 'Success',
                'data' => $courts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: create court
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
                'sport_id' => 'required|exists:sports,id',
                'name' => 'required|string|max:255',
                'price_per_hour' => 'required|numeric|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            $venue = Venue::findOrFail($request->venue_id);

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Bukan venue milik anda'
                ], 403);
            }

            // 🔥 slug unik per venue
            $slug = Str::slug($request->name);

            $count = Court::where('venue_id', $request->venue_id)
                ->where('slug', 'like', "$slug%")
                ->count();

            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            $court = Court::create([
                'venue_id' => $request->venue_id,
                'sport_id' => $request->sport_id,
                'name' => $request->name,
                'price_per_hour' => $request->price_per_hour,
                'status' => $request->status,
                'slug' => $slug,
            ]);

            return response()->json([
                'message' => 'Court berhasil dibuat',
                'data' => $court
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal membuat court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUBLIC: detail court
     */
    public function show(string $id)
    {
        try {
            $court = Court::with(['venue', 'sport', 'images'])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Success',
                'data' => $court
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Court tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil data court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: update court
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = auth()->user();

            $court = Court::findOrFail($id);
            $venue = $court->venue;

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'price_per_hour' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:active,inactive',
                'sport_id' => 'sometimes|exists:sports,id',
            ]);

            // 🔥 update slug kalau name berubah
            if ($request->has('name')) {
                $slug = Str::slug($request->name);

                $count = Court::where('venue_id', $court->venue_id)
                    ->where('slug', 'like', "$slug%")
                    ->where('id', '!=', $court->id)
                    ->count();

                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }

                $court->slug = $slug;
            }

            $court->update($request->only([
                'name',
                'price_per_hour',
                'status',
                'sport_id'
            ]));

            return response()->json([
                'message' => 'Court berhasil diupdate',
                'data' => $court
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Court tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal update court',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: delete court (soft delete)
     */
    public function destroy(string $id)
    {
        try {
            $user = auth()->user();

            $court = Court::findOrFail($id);

            if ($court->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $court->delete();

            return response()->json([
                'message' => 'Court berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Court tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus court',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
