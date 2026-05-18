<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveImageRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      // Salah satu ID wajib ada (Venue atau Court)
      'venue_id' => 'required_without:court_id|exists:venues,id',
      'court_id' => 'required_without:venue_id|exists:courts,id',

      // Validasi file gambar
      'image'    => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
    ];
  }

  /**
   * Custom Messages agar UX Admin enak dibaca
   */
  public function messages(): array
  {
    return [
      'venue_id.required_without' => 'ID Venue harus ada jika ini foto venue.',
      'court_id.required_without' => 'ID Court harus ada jika ini foto lapangan.',
      'image.required' => 'Pilih filenya dulu, Yogi.',
      'image.image'    => 'File harus berupa gambar (jpg, png, jpeg).',
      'image.max'      => 'Gambarnya kegedean! Maksimal 2MB ya.',
    ];
  }
}
