<?php

namespace App\Http\Requests\API\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            // Opsional: tambahkan device_name jika kamu pakai Sanctum untuk mobile
            'device_name' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email jangan dikosongin, nanti masuknya lewat mana?',
            'email.email'    => 'Format emailnya salah tuh.',
            'password.required' => 'Password wajib diisi ya.',
        ];
    }
}
