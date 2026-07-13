<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\BouncedEmail;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesWebhookController extends Controller
{
    /**
     * Handle incoming SNS notifications from SES.
     */
    public function handle(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || empty($payload['Type'])) {
            return response('Invalid payload', 400);
        }

        return match ($payload['Type']) {
            'SubscriptionConfirmation' => $this->confirmSubscription($payload),
            'Notification' => $this->processNotification($payload),
            default => response('OK'),
        };
    }

    /**
     * Auto-confirm SNS topic subscription.
     */
    protected function confirmSubscription(array $payload): Response
    {
        if (!empty($payload['SubscribeURL'])) {
            Http::get($payload['SubscribeURL']);
            Log::info('SES SNS subscription confirmed.', ['TopicArn' => $payload['TopicArn'] ?? null]);
        }

        return response('Subscription confirmed');
    }

    /**
     * Process an SES bounce or complaint notification.
     */
    protected function processNotification(array $payload): Response
    {
        $message = json_decode($payload['Message'] ?? '{}', true);

        if (!is_array($message)) {
            return response('OK');
        }

        $notificationType = $message['notificationType'] ?? $message['eventType'] ?? null;

        match ($notificationType) {
            'Bounce' => $this->handleBounce($message),
            'Complaint' => $this->handleComplaint($message),
            default => null,
        };

        return response('OK');
    }

    /**
     * Record bounced email addresses.
     */
    protected function handleBounce(array $message): void
    {
        $bounce = $message['bounce'] ?? [];
        $bounceType = $bounce['bounceType'] ?? 'Permanent';
        $bounceSubType = $bounce['bounceSubType'] ?? null;

        foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
            $email = strtolower($recipient['emailAddress'] ?? '');
            if (!$email) {
                continue;
            }

            BouncedEmail::updateOrCreate(
                ['email' => $email, 'bounce_type' => $bounceType],
                [
                    'bounce_subtype' => $bounceSubType,
                    'source' => 'sns',
                    'bounced_at' => now(),
                ]
            );

            // Mark matching email logs as bounced.
            EmailLog::where('to', $email)
                ->where('status', 'sent')
                ->latest()
                ->limit(1)
                ->update(['status' => 'bounced', 'error_message' => "Bounce: {$bounceType}/{$bounceSubType}"]);

            Log::warning("SES bounce recorded: {$email} ({$bounceType}/{$bounceSubType})");
        }
    }

    /**
     * Record complaint (spam report) email addresses as permanent bounces.
     */
    protected function handleComplaint(array $message): void
    {
        $complaint = $message['complaint'] ?? [];

        foreach ($complaint['complainedRecipients'] ?? [] as $recipient) {
            $email = strtolower($recipient['emailAddress'] ?? '');
            if (!$email) {
                continue;
            }

            BouncedEmail::updateOrCreate(
                ['email' => $email, 'bounce_type' => 'Complaint'],
                [
                    'bounce_subtype' => $complaint['complaintFeedbackType'] ?? null,
                    'source' => 'sns',
                    'bounced_at' => now(),
                ]
            );

            Log::warning("SES complaint recorded: {$email}");
        }
    }
}
