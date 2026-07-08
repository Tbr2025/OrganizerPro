<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerChangeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{label:string, old:string, new:string}>  $changes
     */
    public function __construct(
        public Tournament $tournament,
        public TournamentRegistration $registration,
        public array $changes,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $who = $this->registration->player->name ?? 'A player';

        return new Envelope(
            subject: "Profile update to review — {$who} ({$this->tournament->name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-change-request',
            with: [
                'playerName' => $this->registration->player->name ?? 'Player',
                'changes' => $this->changes,
                'reviewUrl' => $this->reviewUrl,
                'tournamentName' => $this->tournament->name,
            ],
        );
    }
}
