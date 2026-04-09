<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Cek Autentikasi (Guard tambahan)
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /**
         * 2. Cek Role Admin
         * Tips: Kita pakai logic yang sama dengan API response kamu 
         * supaya SecurityTest bagian "consistent error format" jadi IJO.
         */
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden access. Admin only.',
                'errors' => [
                    'role' => ['You do not have the required admin role.']
                ]
            ], 403);
        }

        // 3. ✅ Lolos, lanjut ke Controller
        return $next($request);
    }
}
