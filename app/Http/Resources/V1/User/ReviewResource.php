<?php

namespace App\Http\Resources\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'         => $this->id,
      'booking_id' => $this->booking_id,
      'rating'     => (int) $this->rating,
      'comment'    => $this->review_text,

      // Flag untuk Frontend (Penting di folder User)
      'is_my_review' => auth()->check() ? $this->user_id === auth()->id() : false,

      // Info User
      'user' => [
        'name'   => $this->user->name ?? 'Anonymous',
        'avatar' => $this->user->avatar_url ?? null,
      ],

      // Konteks Lapangan & Venue
      'venue' => new VenueResource($this->whenLoaded('venue')),
      'court' => new CourtResource($this->whenLoaded('court')),

      'created_at_human' => $this->created_at->diffForHumans(),
      'date'             => $this->created_at->format('d M Y'),
    ];
  }
}
