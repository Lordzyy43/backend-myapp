<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveVenueOperatingHourRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'venue_id'    => 'required|exists:venues,id',
      'day_of_week' => 'required|integer|between:0,6', // 0: Sunday, 6: Saturday
      'open_time'   => 'required|date_format:H:i',
      'close_time'  => 'required|date_format:H:i|after:open_time',
    ];
  }

  public function messages(): array
  {
    return [
      'close_time.after' => 'Jam tutup harus lebih besar dari jam buka, Yogi!',
      'day_of_week.between' => 'Format hari tidak valid (0-6).',
    ];
  }
}
