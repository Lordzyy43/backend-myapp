<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sport;

class SportController extends Controller
{
    /**
     * Get all active sports
     */
    public function index()
    {
        $sports = Sport::active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $sports
        ]);
    }

    /**
     * Store sport (admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:sports,name',
            'icon' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $sport = Sport::create($request->all());

        return response()->json([
            'message' => 'Sport berhasil dibuat',
            'data' => $sport
        ], 201);
    }

    /**
     * Show by slug (frontend friendly)
     */
    public function show($slug)
    {
        $sport = Sport::where('slug', $slug)->firstOrFail();

        return response()->json([
            'message' => 'Success',
            'data' => $sport
        ]);
    }

    /**
     * Update sport
     */
    public function update(Request $request, $id)
    {
        $sport = Sport::findOrFail($id);

        $sport->update($request->all());

        return response()->json([
            'message' => 'Sport berhasil diupdate',
            'data' => $sport
        ]);
    }

    /**
     * Disable sport (soft disable, bukan delete)
     */
    public function destroy($id)
    {
        $sport = Sport::findOrFail($id);

        $sport->update([
            'is_active' => false
        ]);

        return response()->json([
            'message' => 'Sport berhasil dinonaktifkan'
        ]);
    }
}
