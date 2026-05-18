<?php

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveTimeSlotRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Handled by Middleware Admin
  }

  public function rules(): array
  {
    return [
      'start_time'  => 'required|date_format:H:i',
      'end_time'    => 'required|date_format:H:i|after:start_time',
      'label'       => 'nullable|string|max:50',
      'order_index' => 'nullable|integer',
      'is_active'   => 'boolean',
    ];
  }

  public function messages(): array
  {
    return [
      'end_time.after' => 'Jam selesai harus lebih telat dari jam mulai ya sensei!',
      'start_time.date_format' => 'Format jam gunakan HH:mm (Contoh: 08:00)',
    ];
  }
}
