<?php

namespace App\Http\Requests\API\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;
use App\Models\TimeSlot;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'slot_ids' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $bookingDate = Carbon::parse($this->booking_date);

                    if ($bookingDate->isToday()) {
                        $now = now();

                        // Optimasi: Langsung ambil data slot yang dipilih
                        $slots = TimeSlot::whereIn('id', $value)->get();

                        foreach ($slots as $slot) {
                            // Gabungkan Tanggal + Jam Start untuk perbandingan presisi
                            $startDateTime = Carbon::parse($bookingDate->toDateString() . ' ' . $slot->start_time);

                            if ($startDateTime->lt($now)) {
                                $fail("Slot jam {$slot->start_time} sudah terlewat. Pilih jam lain ya!");
                                break; // Cukup satu error untuk menghentikan loop
                            }
                        }
                    }
                },
            ],
            'slot_ids.*' => 'exists:time_slots,id',
            'promo_code' => 'nullable|string|max:50',
        ];
    }

    /**
     * Custom Messages (biar UX bagus)
     */
    public function messages(): array
    {
        return [
            'court_id.required' => 'Court wajib dipilih',
            'court_id.exists' => 'Court tidak valid',

            'booking_date.required' => 'Tanggal booking wajib diisi',
            'booking_date.date' => 'Format tanggal tidak valid',
            'booking_date.after_or_equal' => 'Tanggal tidak boleh di masa lalu',

            'slot_ids.required' => 'Slot wajib dipilih',
            'slot_ids.array' => 'Format slot tidak valid',
            'slot_ids.min' => 'Minimal pilih 1 slot',

            'slot_ids.*.exists' => 'Slot tidak valid',

            'promo_code.max' => 'Kode promo terlalu panjang',
        ];
    }
}
