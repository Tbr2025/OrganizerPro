<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\ActualTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public ActualTeam $team;
    public ?string $welcomeCardPath;

    public function __construct(Player $player, ActualTeam $team, ?string $welcomeCardPath = null)
    {
        $this->player = $player;
        $this->team = $team;
        $this->welcomeCardPath = $welcomeCardPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been approved! - " . $this->team->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-approved',
        );
    }

    public function attachments(): array
    {
        if ($this->welcomeCardPath && file_exists($this->welcomeCardPath)) {
            return [
                Attachment::fromPath($this->welcomeCardPath)
                    ->as('welcome-card.png')
                    ->withMime('image/png'),
            ];
        }

        return [];
    }
}
