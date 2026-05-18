<?php

namespace App\Http\Requests\API\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Pastikan hanya user yang sudah login yang bisa akses.
     */
    public function authorize(): bool
    {
        // Karena ini di folder User, biasanya sudah lewat middleware auth:sanctum
        return auth()->check();
    }

    /**
     * Aturan validasi profil.
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name'  => 'sometimes|string|max:255',

            // Email harus unik, kecuali milik user itu sendiri
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],

            // Phone juga harus unik, kecuali milik user itu sendiri
            'phone' => [
                'sometimes',
                'string',
                'max:15',
                Rule::unique('users')->ignore($userId),
            ],

            // Opsional: Jika user ingin sekalian ganti password
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ];
    }

    /**
     * Custom Messages agar UX di Flutter/React kamu mantap.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai orang lain, Yogi.',
            'phone.unique' => 'Nomor HP ini sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password barunya nggak cocok.',
            'password.min' => 'Password baru minimal 8 karakter ya.',
        ];
    }
}
