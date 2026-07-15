<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailTestController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        // Apply the form values at runtime so "Send Test Email" works
        // even before saving settings.
        if ($request->filled('mail_host')) {
            config(['mail.mailers.smtp.host' => $request->input('mail_host')]);
        }
        if ($request->filled('mail_port')) {
            config(['mail.mailers.smtp.port' => (int) $request->input('mail_port')]);
        }
        if ($request->filled('mail_username')) {
            config(['mail.mailers.smtp.username' => $request->input('mail_username')]);
        }
        if ($request->filled('mail_password')) {
            config(['mail.mailers.smtp.password' => $request->input('mail_password')]);
        }
        if ($request->has('mail_encryption')) {
            config(['mail.mailers.smtp.encryption' => $request->input('mail_encryption') ?: null]);
        }
        if ($request->filled('mail_from_address')) {
            config(['mail.from.address' => $request->input('mail_from_address')]);
        }
        if ($request->filled('mail_from_name')) {
            config(['mail.from.name' => $request->input('mail_from_name')]);
        }

        // Purge the cached SMTP transport so Laravel rebuilds it with new config
        Mail::purge('smtp');

        try {
            Mail::to($request->input('test_email'))->send(new TestMail());

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $request->input('test_email'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 422);
        }
    }
}
