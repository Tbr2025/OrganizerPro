<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogEmailSent
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        if (!$headers->has('X-EmailLog-Id')) {
            return;
        }

        $logId = $headers->get('X-EmailLog-Id')->getBodyAsString();

        EmailLog::where('id', $logId)->update([
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }
}
