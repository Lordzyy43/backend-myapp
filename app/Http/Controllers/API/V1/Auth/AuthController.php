<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use App\Http\Requests\API\V1\Auth\LoginRequest;
use App\Http\Requests\API\V1\Auth\RegisterRequest;
use App\Http\Requests\API\V1\User\UpdateProfileRequest;
use App\Http\Resources\V1\Admin\UserResource;

class AuthController extends Controller
{
    /**
     * REGISTER
     * Menggunakan RegisterRequest untuk validasi ketat.
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Ambil role default (User)
            $role = Role::where('id', User::ROLE_USER)->first()
                ?? Role::where('role_name', 'user')->first();

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password, // Auto-hash via Model Cast
                'phone'    => $request->phone,
                'role_id'  => $role->id,
            ]);

            // Trigger event untuk kirim email verifikasi
            event(new Registered($user));

            return $this->success(
                new UserResource($user),
                'Registrasi berhasil! Silakan cek email untuk verifikasi.',
                200
            );
        } catch (\Exception $e) {
            return $this->error('Gagal registrasi', $e->getMessage(), 500);
        }
    }

    /**
     * LOGIN
     * Menggunakan LoginRequest & Sanctum Token.
     */
    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->error('Email atau password salah', null, 401);
            }

            // Cek verifikasi email
            if (!$user->hasVerifiedEmail()) {
                return $this->error('Email kamu belum diverifikasi nih.', null, 403);
            }

            // Buat token (device_name default ke 'Unknown Device' jika kosong)
            $deviceName = $request->device_name ?? 'Browser/Mobile';
            $token = $user->createToken($deviceName)->plainTextToken;

            return $this->success([
                'token' => $token,
                'user'  => new UserResource($user->load('role')),
            ], 'Login berhasil! Selamat datang kembali.');
        } catch (\Exception $e) {
            return $this->error('Gagal login', $e->getMessage(), 500);
        }
    }

    /**
     * GET PROFILE (ME)
     */
    public function me(Request $request)
    {
        return $this->success(
            ['user' => new UserResource($request->user()->load('role'))],
            'Data profil berhasil diambil.'
        );
    }

    /**
     * UPDATE PROFILE
     * Menggunakan UpdateProfileRequest yang sudah kita buat.
     */
    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();

            // Update data yang dikirim (fillable aman)
            $user->fill($request->validated());

            if ($request->filled('password')) {
                $user->password = $request->password;
            }

            $user->save();

            return $this->success(
                new UserResource($user),
                'Profil berhasil diperbarui!'
            );
        } catch (\Exception $e) {
            return $this->error('Gagal memperbarui profil', $e->getMessage(), 500);
        }
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logout berhasil. Sampai jumpa lagi!');
    }

    /**
     * LOGOUT ALL DEVICES
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return $this->success(null, 'Semua sesi perangkat telah dihentikan.');
    }
}
