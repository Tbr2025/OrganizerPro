<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A status-update email sent to the applicant when an admin sets a
 * registration to "rejected" or "queued" (waitlisted).
 */
class RegistrationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public TournamentRegistration $registration,
        public string $status,          // 'rejected' | 'queued'
        public ?string $recipientName = null,
        public ?string $remarks = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            'rejected' => 'Update on your ' . $this->tournament->name . ' registration',
            'queued'   => 'You are on the waitlist for ' . $this->tournament->name,
            default    => 'Your ' . $this->tournament->name . ' registration status',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-status', with: [
            'tournament' => $this->tournament,
            'registration' => $this->registration,
            'status' => $this->status,
            'recipientName' => $this->recipientName ?: 'Applicant',
            'remarks' => $this->remarks,
        ]);
    }
}
