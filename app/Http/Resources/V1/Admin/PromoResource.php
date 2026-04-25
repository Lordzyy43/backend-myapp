<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'code' => $this->promo_code,
      'description' => $this->description,
      'discount' => [
        'type' => $this->discount_type, // 'percentage' atau 'fixed'
        'value' => (float) $this->discount_value,
        'display' => $this->discount_type === 'percentage'
          ? $this->discount_value . '%'
          : 'Rp ' . number_format($this->discount_value, 0, ',', '.'),
      ],
      'valid_until' => $this->end_date->format('Y-m-d'),
      'is_valid' => $this->isValid(), // Memanggil helper sakti dari Model kamu

      // Metadata untuk Admin (Bisa disembunyikan jika request bukan dari Admin)
      'usage' => $this->when($request->is('api/v1/admin/*'), [
        'limit' => $this->usage_limit,
        'used' => $this->used_count,
        'remaining' => $this->usage_limit ? ($this->usage_limit - $this->used_count) : 'Unlimited',
      ]),
    ];
  }
}
