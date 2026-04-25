<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'rating' => (int) $this->rating,
      'comment' => $this->review_text,

      // Info User (Hanya nama untuk privasi di Public)
      'user' => [
        'name' => $this->user->name ?? 'Anonymous',
        'avatar' => $this->user->avatar_url ?? null, // Jika ada
      ],

      // Konteks: Review ini untuk apa?
      'venue_name' => $this->whenLoaded('venue', fn() => $this->venue->name),
      'court_name' => $this->whenLoaded('court', fn() => $this->court->name),

      'created_at_human' => $this->created_at->diffForHumans(),
      'date' => $this->created_at->format('d M Y'),
    ];
  }
}
