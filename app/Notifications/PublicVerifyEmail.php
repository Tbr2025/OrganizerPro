<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class PublicVerifyEmail extends BaseVerifyEmail
{
    protected function verificationUrl($notifiable)
    {
        URL::temporarySignedRoute(
            'public.verification.verify', // ✅ use your custom route name
            Carbon::now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Click the button below to verify your email address and complete your registration.')
            ->action('Verify Email', $this->verificationUrl($notifiable))
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
