<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // ❌ Belum login
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // ❌ Bukan admin
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Forbidden (Admin only)'
            ], 403);
        }

        // ✅ Lolos
        return $next($request);
    }
}
