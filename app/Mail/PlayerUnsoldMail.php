<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerUnsoldMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public Auction $auction;

    public function __construct(Player $player, Auction $auction)
    {
        $this->player = $player;
        $this->auction = $auction;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Auction Update — " . $this->auction->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-unsold',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
