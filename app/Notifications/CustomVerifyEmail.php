<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends Notification
{
    use Queueable;

    protected $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verifyUrl = URL::temporarySignedRoute(
            'public.verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->id, 'hash' => sha1($notifiable->email)]
        );

        return (new MailMessage)
            ->subject('Verify Your Email Address & Login Password')
            ->line('Thank you for registering. Please verify your email address and find your login password below.')
            ->line('**Your temporary password:** `' . $this->password . '`')
            ->line('You can change it after logging in.')
            ->action('Verify Email', $verifyUrl)
            ->line('If you did not sign up, no further action is required.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
