<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tournament $tournament;
    public TournamentRegistration $registration;
    public string $registrationType;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, TournamentRegistration $registration, string $type)
    {
        $this->tournament = $tournament;
        $this->registration = $registration;
        $this->registrationType = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $type = ucfirst($this->registrationType);
        return new Envelope(
            subject: "New {$type} Registration - {$this->tournament->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-registration-admin',
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
