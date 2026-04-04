<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'phone' => 'nullable|string|max:20',
            ]);

            $role = Role::where('role_name', 'user')->firstOrFail();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // 🔥 auto hash via cast
                'phone' => $validated['phone'] ?? null,
                'role_id' => $role->id,
                'email_verified_at' => null,
            ]);

            // 🔥 Trigger email verification
            event(new Registered($user));

            return $this->success(null, 'Register berhasil, silakan verifikasi email');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal register', $e->getMessage(), 500);
        }
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                'device_name' => 'required|string|max:255', // 🔥 penting
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return $this->error('Email atau password salah', null, 401);
            }

            // 🔥 BLOCK kalau belum verify
            if (!$user->email_verified_at) {
                return $this->error('Email belum diverifikasi', null, 403);
            }

            // 🔥 create token per device
            $token = $user->createToken($validated['device_name'])->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => $user->load('role')
            ], 'Login berhasil');
        } catch (ValidationException $e) {
            return $this->error('Validasi gagal', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->error('Gagal login', $e->getMessage(), 500);
        }
    }

    /**
     * LOGOUT (CURRENT DEVICE ONLY)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->success(null, 'Logout berhasil');
        } catch (\Exception $e) {
            return $this->error('Gagal logout', $e->getMessage(), 500);
        }
    }

    /**
     * LOGOUT ALL DEVICES
     */
    public function logoutAll(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            return $this->success(null, 'Logout semua device berhasil');
        } catch (\Exception $e) {
            return $this->error('Gagal logout semua device', $e->getMessage(), 500);
        }
    }

    /**
     * GET CURRENT USER
     */
    public function me(Request $request)
    {
        try {
            return $this->success(
                ['user' => $request->user()->load('role')],
                'Data user berhasil diambil'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal mengambil data user', $e->getMessage(), 500);
        }
    }
}
