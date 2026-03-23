<?php

namespace App\Mail;

use App\Models\ActualTeam;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamManagerCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $password;
    public Tournament $tournament;
    public ActualTeam $team;

    public function __construct(User $user, string $password, Tournament $tournament, ActualTeam $team)
    {
        $this->user = $user;
        $this->password = $password;
        $this->tournament = $tournament;
        $this->team = $team;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Team Manager Account - ' . $this->tournament->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-manager-credentials',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
