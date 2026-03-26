<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Venue;

class VenueController extends Controller
{
    /**
     * PUBLIC: list semua venue
     */
    public function index()
    {
        try {
            $venues = Venue::with('courts')
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $venues
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: buat venue
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->isOwner() && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'description' => 'nullable|string',
            ]);

            $slug = Str::slug($request->name);

            // 🔥 handle duplicate slug
            $count = Venue::where('slug', 'like', "$slug%")->count();
            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }

            $venue = Venue::create([
                'owner_id' => $user->id,
                'name' => $request->name,
                'address' => $request->address,
                'city' => $request->city,
                'description' => $request->description,
                'slug' => $slug,
            ]);

            return response()->json([
                'message' => 'Venue berhasil dibuat',
                'data' => $venue
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal membuat venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUBLIC: detail venue
     */
    public function show(string $id)
    {
        try {
            $venue = Venue::with(['courts', 'images', 'operatingHours'])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Success',
                'data' => $venue
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Venue tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil data venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: update venue
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = auth()->user();

            $venue = Venue::findOrFail($id);

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:100',
                'description' => 'nullable|string',
            ]);

            if ($request->has('name')) {
                $slug = Str::slug($request->name);

                $count = Venue::where('slug', 'like', "$slug%")
                    ->where('id', '!=', $venue->id)
                    ->count();

                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }

                $venue->slug = $slug;
            }

            $venue->update($request->only([
                'name',
                'address',
                'city',
                'description'
            ]));

            return response()->json([
                'message' => 'Venue berhasil diupdate',
                'data' => $venue
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Venue tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal update venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OWNER: delete venue
     */
    public function destroy(string $id)
    {
        try {
            $user = auth()->user();

            $venue = Venue::findOrFail($id);

            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $venue->delete();

            return response()->json([
                'message' => 'Venue berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Venue tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
