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
    public ?string $password;
    public Tournament $tournament;
    public ActualTeam $team;
    public string $roleName;
    public bool $isNewUser;

    public function __construct(User $user, ?string $password, Tournament $tournament, ActualTeam $team, string $roleName = 'Team Manager')
    {
        $this->user = $user;
        $this->password = $password;
        $this->tournament = $tournament;
        $this->team = $team;
        $this->roleName = $roleName;
        $this->isNewUser = $password !== null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . $this->roleName . ' Account - ' . $this->tournament->name,
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
