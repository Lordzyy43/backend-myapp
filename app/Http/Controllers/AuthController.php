<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'phone' => 'nullable|string|max:20',
            ]);

            // 🔥 ambil role default: user
            $role = Role::where('role_name', 'user')->first();

            if (!$role) {
                return response()->json([
                    'message' => 'Role user tidak ditemukan'
                ], 500);
            }

            // 🔥 tidak perlu Hash::make karena sudah di-cast
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'role_id' => $role->id,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Register berhasil',
                'token' => $token,
                'data' => $user->load('role')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal register',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            // 🔥 pakai hash check manual (karena cast hanya saat save)
            if (!$user || !\Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Email atau password salah'
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login berhasil',
                'token' => $token,
                'data' => $user->load('role')
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout berhasil'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET CURRENT USER
     */
    public function me(Request $request)
    {
        try {
            return response()->json([
                'message' => 'Success',
                'data' => $request->user()->load('role')
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Gagal mengambil data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
