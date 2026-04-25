<?php

namespace App\Http\Controllers\API\V1\Admin;

use Illuminate\Http\Request;
use App\Models\VenueOperatingHour;
use App\Models\Venue;

class VenueOperatingHourController extends Controller
{
    /**
     * GET operating hours by venue
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'venue_id' => 'required|exists:venues,id',
            ]);

            $hours = VenueOperatingHour::where('venue_id', $request->venue_id)
                ->orderBy('day_of_week')
                ->get();

            return response()->json([
                'message' => 'Success',
                'data' => $hours
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data jam operasional',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CREATE operating hour
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
                'day_of_week' => 'required|integer|min:0|max:6',
                'open_time' => 'required|date_format:H:i',
                'close_time' => 'required|date_format:H:i|after:open_time',
            ]);

            $venue = Venue::findOrFail($request->venue_id);

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Bukan venue milik anda'
                ], 403);
            }

            // 🔥 prevent duplicate day
            $exists = VenueOperatingHour::where('venue_id', $request->venue_id)
                ->where('day_of_week', $request->day_of_week)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Hari tersebut sudah diatur'
                ], 409);
            }

            $hour = VenueOperatingHour::create([
                'venue_id' => $request->venue_id,
                'day_of_week' => $request->day_of_week,
                'open_time' => $request->open_time,
                'close_time' => $request->close_time,
            ]);

            return response()->json([
                'message' => 'Jam operasional berhasil ditambahkan',
                'data' => $hour
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menambahkan jam operasional',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE operating hour
     */
    public function update(Request $request, string $id)
    {
        try {
            $user = auth()->user();

            if (!$user || (!$user->isOwner() && !$user->isAdmin())) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $hour = VenueOperatingHour::findOrFail($id);
            $venue = $hour->venue;

            // 🔥 ownership check
            if ($venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $request->validate([
                'day_of_week' => 'sometimes|integer|min:0|max:6',
                'open_time' => 'sometimes|date_format:H:i',
                'close_time' => 'sometimes|date_format:H:i|after:open_time',
            ]);

            // 🔥 handle duplicate day saat update
            if ($request->has('day_of_week')) {
                $exists = VenueOperatingHour::where('venue_id', $venue->id)
                    ->where('day_of_week', $request->day_of_week)
                    ->where('id', '!=', $hour->id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'message' => 'Hari tersebut sudah ada'
                    ], 409);
                }
            }

            $hour->update($request->only([
                'day_of_week',
                'open_time',
                'close_time'
            ]));

            return response()->json([
                'message' => 'Jam operasional berhasil diupdate',
                'data' => $hour
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal update jam operasional',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE operating hour
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

            $hour = VenueOperatingHour::findOrFail($id);

            if ($hour->venue->owner_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'message' => 'Forbidden'
                ], 403);
            }

            $hour->delete();

            return response()->json([
                'message' => 'Jam operasional berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal menghapus',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
