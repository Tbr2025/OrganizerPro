<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'assigned'|'updated'|'removed'  $mode
     * @param  array<int,string>  $tournaments
     * @param  array<int,string>  $teams
     * @param  array<int,string>  $matches
     */
    public function __construct(
        public User $user,
        public string $mode,
        public array $tournaments = [],
        public array $teams = [],
        public array $matches = [],
        public ?string $password = null,
    ) {}

    public function envelope(): Envelope
    {
        $brand = config('settings.app_name') ?: config('app.name');
        $subject = match ($this->mode) {
            'removed' => "Your organizer access has been updated — {$brand}",
            'updated' => "Your organizer assignments were updated — {$brand}",
            default => "You've been assigned as an organizer — {$brand}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organizer-assignment',
            with: [
                'mode' => $this->mode,
                'tournaments' => $this->tournaments,
                'teams' => $this->teams,
                'matches' => $this->matches,
                'password' => $this->password,
                'loginUrl' => url('/login'),
            ],
        );
    }
}
