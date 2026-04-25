<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class MidtransService
{
  public function __construct()
  {
    Config::$serverKey = config('services.midtrans.server_key');
    Config::$isProduction = config('services.midtrans.is_production');
    Config::$isSanitized = config('services.midtrans.is_sanitized', true);
    Config::$is3ds = config('services.midtrans.is_3ds', true);
  }

  /**
   * Membuat Transaksi Midtrans
   */
  public function createTransaction(Booking $booking)
  {
    $booking->loadMissing(['user', 'court']);

    // Kita buat ID yang unik untuk Midtrans tapi tetap mengandung Booking Code
    // Format: BOOK-20260425-XXXXX-1714000000
    $orderId = $booking->booking_code . '-' . time();

    $params = [
      'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => (int) $booking->final_price,
      ],
      'customer_details' => [
        'first_name' => $booking->user->name,
        'email' => $booking->user->email,
        // Tambahkan phone jika ada di model user kamu
        'phone' => $booking->user->phone ?? '',
      ],
      'item_details' => [
        [
          'id' => $booking->court_id,
          'price' => (int) $booking->final_price,
          'quantity' => 1,
          'name' => "Sewa: " . $this->limitString($booking->court->name, 45),
        ]
      ],
      'expiry' => [
        'start_time' => now()->format('Y-m-d H:i:s O'),
        'unit' => 'minutes',
        'duration' => 60
      ],
    ];

    try {
      $response = Snap::createTransaction($params);

      return [
        'token' => $response->token,
        'redirect_url' => $response->redirect_url,
        'order_id' => $orderId, // Kembalikan order_id yang baru dibuat
      ];
    } catch (\Exception $e) {
      Log::error("Midtrans Snap Error: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Helper untuk membatasi panjang string agar tidak kena limit Midtrans (Max 50 char)
   */
  private function limitString($string, $limit = 50)
  {
    return strlen($string) > $limit ? substr($string, 0, $limit - 3) . '...' : $string;
  }

  public function handleNotification()
  {
    try {
      // Ini akan otomatis membaca php://input (body request dari Midtrans)
      return new Notification();
    } catch (\Exception $e) {
      Log::error("Midtrans Webhook Error: " . $e->getMessage());
      return null;
    }
  }
}
