<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemNotice extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'bell',
        public string $level = 'info',   // info | success | warning | danger
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
            'level' => $this->level,
        ];
    }
}
