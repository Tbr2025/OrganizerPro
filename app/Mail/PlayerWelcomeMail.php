<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Player; // Assuming you have a Player model
use App\Models\Tournament;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment; // Import the Attachment class
use Illuminate\Queue\SerializesModels;

class PlayerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Player $player;
    public string $filePath;
    public ?Tournament $tournament;
    public string $type;
    public array $overrides;

    /**
     * Create a new message instance.
     *
     * @param Player $player The player receiving the welcome card.
     * @param string $filePath The absolute path to the image file to attach.
     * @param ?Tournament $tournament The tournament context (for per-tournament templates).
     * @param string $type The email template type to resolve.
     * @param array $overrides Extra placeholder overrides (e.g. {team_name}).
     */
    public function __construct(
        Player $player,
        string $filePath,
        ?Tournament $tournament = null,
        string $type = EmailTemplate::TYPE_WELCOME_CARD,
        array $overrides = []
    ) {
        $this->player = $player;
        $this->filePath = $filePath;
        $this->tournament = $tournament;
        $this->type = $type;
        $this->overrides = $overrides;
    }

    /** @var array{subject:string, html:string}|null */
    private ?array $resolved = null;

    private function resolved(): array
    {
        if ($this->resolved === null) {
            $this->resolved = app(EmailTemplateService::class)->resolve(
                $this->type,
                $this->tournament,
                null,
                $this->player,
                $this->overrides,
            );
        }

        return $this->resolved;
    }

    /**
     * Get the message envelope.
     * Defines the subject line and other headers.
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
        // Only attach when a real poster file was generated.
        if ($this->filePath === '' || ! is_file($this->filePath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->filePath)
                ->as('welcome-card.png') // This is the name the recipient will see
                ->withMime('image/png'),
        ];
    }
}