<?php

namespace App\Http\Requests\API\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'nullable|string|max:15|unique:users',
            'password' => 'required|string|min:8',
            // Pastikan role yang dikirim valid (biasanya default ke ROLE_USER)
            'role_id'  => 'nullable|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah dipakai, Yogi. Lupa password?',
            'phone.unique' => 'Nomor telepon sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password nggak cocok nih.',
            'password.min' => 'Password minimal 8 karakter ya.',
        ];
    }
}
