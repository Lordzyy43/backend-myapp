<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveVenueRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    // Cek apakah ini aksi update (PUT/PATCH) atau create (POST)
    $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
    $venueId = $this->route('venue') ? $this->route('venue')->id : null;

    return [
      // Jika update gunakan 'sometimes', jika create gunakan 'required'
      'name'        => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255|unique:venues,name,' . $venueId,
      'address'     => ($isUpdate ? 'sometimes' : 'required') . '|string|max:500',
      'city'        => ($isUpdate ? 'sometimes' : 'required') . '|string|max:100',
      'description' => 'nullable|string',
      'slug'        => 'nullable|string|max:255|unique:venues,slug,' . $venueId,
      'owner_id'    => ($isUpdate ? 'sometimes' : 'nullable') . '|exists:users,id',
    ];
  }

  public function messages(): array
  {
    return [
      'name.required'    => 'Nama venue jangan dikosongin ya!',
      'name.unique'      => 'Nama venue ini sudah terdaftar, cari nama lain yuk.',
      'address.required' => 'Alamat lengkap wajib diisi biar user nggak nyasar.',
      'city.required'    => 'Kota mana nih? Wajib diisi ya.',
      'owner_id.exists'  => 'Owner tidak ditemukan di database.',
    ];
  }
}
