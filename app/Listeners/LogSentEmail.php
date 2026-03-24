<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        $to = collect($message->getTo())->map(fn ($address) => $address->getAddress())->implode(', ');
        $subject = $message->getSubject() ?? '';

        // Try to extract mailable class name
        $mailableClass = null;
        $headers = $message->getHeaders();
        if ($headers->has('X-Laravel-Mailable')) {
            $mailableClass = $headers->get('X-Laravel-Mailable')->getBodyAsString();
        }

        EmailLog::create([
            'to' => $to,
            'subject' => $subject,
            'mailable_class' => $mailableClass,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
