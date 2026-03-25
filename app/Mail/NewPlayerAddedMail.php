<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\ActualTeam;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewPlayerAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public ActualTeam $team;
    public User $addedBy;
    public ?string $addedByPhone;

    /**
     * Create a new message instance.
     */
    public function __construct(Player $player, ActualTeam $team, User $addedBy, ?string $addedByPhone = null)
    {
        $this->player = $player;
        $this->team = $team;
        $this->addedBy = $addedBy;
        $this->addedByPhone = $addedByPhone;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . $this->team->name . '!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-player-added',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
