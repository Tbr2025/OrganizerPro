<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlayerUpdatedNotification extends Notification
{
     use Queueable;

    protected $player;
    protected $updatedBy;
    protected $pageUrl;

    public function __construct($player, $updatedBy, $pageUrl)
    {
        $this->player = $player;
        $this->updatedBy = $updatedBy;
        $this->pageUrl = $pageUrl;
    }

    public function via($notifiable)
    {
        return ['database']; // store in notifications table
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Player '{$this->player->name}' was updated by {$this->updatedBy->name}.",
            'player_id' => $this->player->id,
            'updated_by_id' => $this->updatedBy->id,
            'updated_by_name' => $this->updatedBy->name,
            'page' => $this->pageUrl,
            'time' => now()->toDateTimeString()
        ];
    }
}
