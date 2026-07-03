<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationCorrectionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $unverifiedFields  Human-readable labels of fields needing correction.
     */
    public function __construct(
        public Tournament $tournament,
        public TournamentRegistration $registration,
        public array $unverifiedFields,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action needed: please review your registration - {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-correction',
            with: [
                'applicantName' => $this->registration->player->name
                    ?? $this->registration->captain_name
                    ?? 'Applicant',
                'fields' => $this->unverifiedFields,
                'note' => $this->note,
                'tournamentName' => $this->tournament->name,
            ],
        );
    }
}
