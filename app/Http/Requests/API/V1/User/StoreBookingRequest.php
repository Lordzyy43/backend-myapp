<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Authorization (biarkan true, karena kita handle di Policy)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation Rules
     */
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
                    // Ambil tanggal booking dari input
                    $bookingDate = \Carbon\Carbon::parse($this->booking_date);

                    // Jika booking untuk HARI INI
                    if ($bookingDate->isToday()) {
                        $now = now();

                        // Cek apakah ada slot yang jam start-nya sudah lewat
                        $passedSlots = \App\Models\TimeSlot::whereIn('id', $value)
                            ->get()
                            ->filter(function ($slot) use ($now) {
                                // Bandingkan jam sekarang dengan jam mulai slot
                                return \Carbon\Carbon::parse($slot->start_time)->lt($now);
                            });

                        if ($passedSlots->isNotEmpty()) {
                            $fail('Beberapa slot yang dipilih sudah melewati waktu saat ini.');
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
