<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'             => $this->id,
      'promo_code'     => $this->promo_code,
      'description'    => $this->description,
      'discount_type'  => $this->discount_type, // 'percentage' atau 'fixed'
      'discount_value' => (float) $this->discount_value,
      'valid_until'    => $this->end_date->format('d M Y'),
      'is_valid'       => $this->isValid(), // Menggunakan fungsi dari Model kamu
    ];
  }
}
