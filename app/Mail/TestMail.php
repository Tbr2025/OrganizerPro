<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email from ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        $appName = config('app.name');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #333;'>Mail Configuration Test</h2>
                    <p>This is a test email from <strong>{$appName}</strong>.</p>
                    <p>If you are receiving this email, your mail configuration is working correctly.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #888; font-size: 12px;'>
                        Sent via: {$host}:{$port}
                    </p>
                </div>
            ",
        );
    }
}
