<?php

namespace App\Http\Resources\V1\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'      => $this->id,
      'type'    => $this->type, // Misal: 'booking_success', 'promo_info'
      'title'   => $this->title,
      'message' => $this->message,

      // Mengambil ID booking jika notifiable adalah booking
      'related_id' => $this->notifiable_id,
      'action_url' => $this->action_url,

      'is_read' => (bool) $this->is_read,
      'data'    => $this->data, // Extra info jika ada

      'created_at_human' => $this->created_at->diffForHumans(),
      'created_at'       => $this->created_at->toDateTimeString(),
      'read_at'          => $this->read_at ? $this->read_at->toDateTimeString() : null,
    ];
  }
}
