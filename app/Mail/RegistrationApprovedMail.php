<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tournament $tournament;
    public TournamentRegistration $registration;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, TournamentRegistration $registration)
    {
        $this->tournament = $tournament;
        $this->registration = $registration;
    }

    /** @var array{subject:string, html:string}|null */
    private ?array $resolved = null;

    private function resolved(): array
    {
        if ($this->resolved === null) {
            $this->resolved = app(EmailTemplateService::class)->resolve(
                EmailTemplate::TYPE_APPROVED,
                $this->tournament,
                $this->registration,
            );
        }

        return $this->resolved;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolved()['subject']);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(htmlString: $this->resolved()['html']);
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
