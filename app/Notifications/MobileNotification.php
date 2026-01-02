<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MobileNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $type,
        private readonly array $data = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return [FcmChannel::class, 'database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => array_merge(['type' => $this->type], $this->data),
        ];
    }
}
