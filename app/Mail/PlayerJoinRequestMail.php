<?php

namespace App\Mail;

use App\Models\ActualTeam;
use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerJoinRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public ActualTeam $team;
    public string $dashboardUrl;

    public function __construct(Player $player, ActualTeam $team, string $dashboardUrl)
    {
        $this->player = $player;
        $this->team = $team;
        $this->dashboardUrl = $dashboardUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Player Request - ' . $this->player->name . ' wants to join ' . $this->team->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-join-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
