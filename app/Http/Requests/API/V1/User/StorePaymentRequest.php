<?php

namespace App\Http\Requests\API\V1\User;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Authorize: Cek apakah user berhak membayar booking ini.
     */
    public function authorize(): bool
    {
        $booking = Booking::find($this->booking_id);

        // 1. Booking harus ada
        // 2. Booking harus milik user yang sedang login
        // 3. Booking TIDAK boleh sudah lunas (mencegah double payment)
        return $booking
            && $booking->user_id == auth()->id()
            && !$booking->payment?->isPaid();
    }

    /**
     * Rules: Validasi input data.
     */
    public function rules(): array
    {
        return [
            'booking_id' => [
                'required',
                'exists:bookings,id',
                function ($attribute, $value, $fail) {
                    $booking = Booking::find($value);
                    if ($booking && $booking->status_id === \App\Models\BookingStatus::expired()) {
                        $fail('Yah, booking ini sudah kadaluarsa. Silakan buat booking baru ya!');
                    }
                },
            ],
            'payment_method' => [
                'required',
                'string',
                // Batasi method yang tersedia agar tidak asal tembak API
                Rule::in(['midtrans', 'bank_transfer', 'e_wallet']),
            ],
        ];
    }

    /**
     * Custom Messages
     */
    public function messages(): array
    {
        return [
            'booking_id.required' => 'Booking ID mana yang mau dibayar?',
            'booking_id.exists'   => 'Data booking tidak ditemukan.',
            'payment_method.required' => 'Pilih metode pembayaran dulu ya.',
            'payment_method.in'   => 'Metode pembayaran belum tersedia.',
        ];
    }
}
