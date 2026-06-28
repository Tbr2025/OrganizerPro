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

/**
 * Sent to the applicant immediately after they submit a player/team
 * registration: "your application is under review / you are in queue".
 */
class ApplicationUnderReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tournament $tournament;
    public TournamentRegistration $registration;
    public string $applicantName;

    public function __construct(Tournament $tournament, TournamentRegistration $registration, string $applicantName)
    {
        $this->tournament = $tournament;
        $this->registration = $registration;
        $this->applicantName = $applicantName;
    }

    /** @var array{subject:string, html:string}|null */
    private ?array $resolved = null;

    private function resolved(): array
    {
        if ($this->resolved === null) {
            $this->resolved = app(EmailTemplateService::class)->resolve(
                EmailTemplate::TYPE_UNDER_REVIEW,
                $this->tournament,
                $this->registration,
                null,
                ['{applicant_name}' => e($this->applicantName)],
            );
        }

        return $this->resolved;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolved()['subject']);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->resolved()['html']);
    }

    public function attachments(): array
    {
        return [];
    }
}
