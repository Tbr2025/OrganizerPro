<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification
{
    use Queueable;

    protected string $message;
    protected ?string $page;
    protected string $icon;

    public function __construct(string $message, ?string $page = null, string $icon = 'info')
    {
        $this->message = $message;
        $this->page = $page;
        $this->icon = $icon;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->message,
            'page' => $this->page,
            'icon' => $this->icon,
            'time' => now()->toDateTimeString(),
        ];
    }
}
