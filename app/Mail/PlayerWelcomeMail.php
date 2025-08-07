<?php

namespace App\Mail;

use App\Models\Player; // Assuming you have a Player model
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    /**
     * Create a new message instance.
     *
     * @param Player $player The player receiving the welcome card.
     * @param string $filePath The absolute path to the image file to attach.
     */
    public function __construct(Player $player, string $filePath)
    {
        $this->player = $player;
        $this->filePath = $filePath;
    }

    /**
     * Get the message envelope.
     * Defines the subject line and other headers.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your OrganizerPro Welcome Card!',
        );
    }

    /**
     * Get the message content definition.
     * Defines the Blade view for the email body.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-card', // We will create this Blade view next
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // This is where the attachment is added.
        return [
            Attachment::fromPath($this->filePath)
                ->as('welcome-card.png') // This is the name the recipient will see
                ->withMime('image/png'),
        ];
    }
}