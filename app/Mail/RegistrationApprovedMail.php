<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tournament $tournament;
    public TournamentRegistration $registration;
    public ?string $posterPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, TournamentRegistration $registration, ?string $posterPath = null)
    {
        $this->tournament = $tournament;
        $this->registration = $registration;
        $this->posterPath = $posterPath;
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
        $html = $this->resolved()['html'];

        // Inject welcome card inline before </body> if poster exists
        if ($this->posterPath && $this->posterFullPath() && is_file($this->posterFullPath())) {
            $base64 = base64_encode(file_get_contents($this->posterFullPath()));
            $mime = mime_content_type($this->posterFullPath()) ?: 'image/png';
            $imgTag = '<div style="text-align:center;margin:24px 0;"><img src="data:' . $mime . ';base64,' . $base64 . '" alt="Welcome Card" style="max-width:100%;height:auto;border-radius:8px;" /></div>';

            if (stripos($html, '</body>') !== false) {
                $html = str_ireplace('</body>', $imgTag . '</body>', $html);
            } else {
                $html .= $imgTag;
            }
        }

        return new Content(htmlString: $html);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if ($this->posterPath && $this->posterFullPath() && is_file($this->posterFullPath())) {
            return [
                Attachment::fromPath($this->posterFullPath())
                    ->as('welcome-card.png')
                    ->withMime('image/png'),
            ];
        }

        return [];
    }

    /**
     * Resolve the full filesystem path to the poster.
     */
    private function posterFullPath(): ?string
    {
        if (!$this->posterPath) {
            return null;
        }

        return storage_path('app/public/' . $this->posterPath);
    }
}
