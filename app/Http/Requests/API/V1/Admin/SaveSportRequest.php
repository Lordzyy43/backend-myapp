<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveSportRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Pastikan sudah dihandle middleware IsAdmin
  }

  public function rules(): array
  {
    $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
    // Ambil ID sport dari route untuk validasi unique: sports,slug,{id}
    $sportId = $this->route('sport') ? $this->route('sport')->id : null;

    return [
      'name'       => ($isUpdate ? 'sometimes' : 'required') . '|string|max:100|unique:sports,name,' . $sportId,
      'slug'       => 'nullable|string|max:100|unique:sports,slug,' . $sportId,
      'icon'       => 'nullable|image|mimes:png,jpg,jpeg,svg|max:1024',
      'image'      => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
      'is_active'  => 'nullable|boolean',
      'sort_order' => 'nullable|integer|min:0',
    ];
  }

  public function messages(): array
  {
    return [
      'name.unique' => 'Nama olahraga sudah ada, coba nama lain.',
      'icon.image'  => 'Icon harus berupa gambar.',
      'icon.max'    => 'Ukuran icon maksimal 1MB.',
    ];
  }
}
