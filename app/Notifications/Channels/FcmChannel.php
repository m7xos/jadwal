<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private readonly FcmService $fcm)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        if (! is_array($message)) {
            return;
        }

        $tokens = method_exists($notifiable, 'routeNotificationForFcm')
            ? (array) $notifiable->routeNotificationForFcm()
            : [];

        $title = (string) ($message['title'] ?? '');
        $body = (string) ($message['body'] ?? '');
        $data = is_array($message['data'] ?? null) ? $message['data'] : [];

        if ($title === '' && $body === '') {
            return;
        }

        $this->fcm->send($tokens, $title, $body, $data);
    }
}
