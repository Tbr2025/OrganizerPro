<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileChangesRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{label:string, value:string}>  $changes
     */
    public function __construct(
        public Tournament $tournament,
        public TournamentRegistration $registration,
        public array $changes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your profile change request was not approved — {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-changes-rejected',
            with: [
                'playerName' => $this->registration->player->name ?? 'Player',
                'changes' => $this->changes,
                'tournamentName' => $this->tournament->name,
                'tournament' => $this->tournament,
            ],
        );
    }
}
