<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function send(
        $userId,
        $type,
        $title,
        $message,
        $notifiable = null,
        $actionUrl = null,
        $data = []
    ) {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'notifiable_id' => $notifiable?->id,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }
}
