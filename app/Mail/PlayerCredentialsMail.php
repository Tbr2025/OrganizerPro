<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public ?Tournament $tournament = null,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->tournament?->name ?? config('app.name');

        return new Envelope(subject: "Your login details — {$name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.player-credentials');
    }
}
