<?php

namespace App\Mail;

use App\Models\Player;
use App\Models\ActualTeam;
use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerSoldMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public ActualTeam $team;
    public Auction $auction;
    public float $finalPrice;

    public function __construct(Player $player, ActualTeam $team, Auction $auction, float $finalPrice)
    {
        $this->player = $player;
        $this->team = $team;
        $this->auction = $auction;
        $this->finalPrice = $finalPrice;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been selected! — " . $this->auction->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-sold',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
