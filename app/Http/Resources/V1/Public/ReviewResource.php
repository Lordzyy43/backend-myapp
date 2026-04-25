<?php

namespace App\Http\Resources\V1\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'          => $this->id,
      'user_name'   => $this->user->name ?? 'Anonymous', // Ambil nama dari relasi user
      'rating'      => (int) $this->rating,
      'review_text' => $this->review_text,
      'date'        => $this->created_at->diffForHumans(), // "2 days ago" agar lebih modern

      // Opsional: Jika ingin tahu ini review untuk lapangan mana
      'court_name'  => $this->whenLoaded('court', fn() => $this->court->name),
    ];
  }
}
