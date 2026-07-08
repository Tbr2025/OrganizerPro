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
     * @param  array<int, string>  $acceptedGroups  Section titles that are fully verified.
     * @param  array<int, array{section:string, fields:array<int,string>}>  $pendingGroups  Sections with fields still to review.
     */
    public function __construct(
        public Tournament $tournament,
        public TournamentRegistration $registration,
        public array $acceptedGroups = [],
        public array $pendingGroups = [],
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $allAccepted = empty($this->pendingGroups);

        return new Envelope(
            subject: $allAccepted
                ? "Your registration is verified - {$this->tournament->name}"
                : "Action needed: please review your registration - {$this->tournament->name}",
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
                'acceptedGroups' => $this->acceptedGroups,
                'pendingGroups' => $this->pendingGroups,
                'note' => $this->note,
                'tournamentName' => $this->tournament->name,
            ],
        );
    }
}
