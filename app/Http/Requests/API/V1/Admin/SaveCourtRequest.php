<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveCourtRequest extends FormRequest
{
  /**
   * Authorize user (handled by IsAdmin middleware)
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   */
  public function rules(): array
  {
    // Cek apakah aksi saat ini adalah Update (PUT/PATCH)
    $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

    // Ambil ID dari route jika sedang update (misal: /admin/courts/{court})
    // Kita gunakan $this->route('court') karena Laravel otomatis mengikat ID ke Model di Route.
    $courtId = $this->route('court') instanceof \App\Models\Court
      ? $this->route('court')->id
      : $this->route('court');

    return [
      // Jika Update, venue_id & sport_id jadi optional (sometimes)
      'venue_id'       => ($isUpdate ? 'sometimes' : 'required') . '|exists:venues,id',
      'sport_id'       => ($isUpdate ? 'sometimes' : 'required') . '|exists:sports,id',

      'name'           => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255',
      'price_per_hour' => ($isUpdate ? 'sometimes' : 'required') . '|numeric|min:0',
      'status'         => ($isUpdate ? 'sometimes' : 'required') . '|in:active,inactive,maintenance',

      // Slug unik kecuali untuk ID miliknya sendiri saat update
      'slug'           => [
        'nullable',
        'string',
        'max:255',
        'unique:courts,slug,' . $courtId
      ],
    ];
  }

  /**
   * Custom Messages
   */
  public function messages(): array
  {
    return [
      'venue_id.exists' => 'Venue tidak ditemukan.',
      'sport_id.exists' => 'Jenis olahraga tidak valid.',
      'status.in'       => 'Status harus salah satu dari: active, inactive, atau maintenance.',
      'price_per_hour.numeric' => 'Harga harus berupa angka ya!',
      'slug.unique'     => 'Slug ini sudah dipakai lapangan lain.',
      'name.required'   => 'Nama lapangan wajib diisi.',
    ];
  }
}
