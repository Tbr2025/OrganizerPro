<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;

class LogEmailSending
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $to = collect($message->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->implode(', ');

        $subject = $message->getSubject() ?? '';

        $mailableClass = null;
        $headers = $message->getHeaders();
        if ($headers->has('X-Laravel-Mailable')) {
            $mailableClass = $headers->get('X-Laravel-Mailable')->getBodyAsString();
        }

        $bodyHtml = $message->getHtmlBody();

        $log = EmailLog::create([
            'to' => $to,
            'subject' => $subject,
            'mailable_class' => $mailableClass,
            'status' => EmailLog::STATUS_PENDING,
            'body_html' => $bodyHtml,
        ]);

        // Stash the log ID in a custom header so the Sent listener can find it
        $message->getHeaders()->addTextHeader('X-EmailLog-Id', (string) $log->id);
    }
}
