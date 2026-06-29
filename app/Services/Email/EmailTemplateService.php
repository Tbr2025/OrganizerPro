<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\SettingService;
use Illuminate\Support\Carbon;

class EmailTemplateService
{
    public function __construct(private SettingService $settings)
    {
    }

    /**
     * Previewable / editable email types, with labels, default subjects,
     * and the placeholder tokens available to each (for the editor cheat-sheet).
     */
    public function types(): array
    {
        $common = [
            '{brand_name}', '{tournament_name}', '{tournament_logo}',
            '{tournament_start_date}', '{tournament_location}', '{tournament_url}',
            '{primary_color}', '{secondary_color}', '{header_text_color}', '{app_url}',
        ];

        return [
            EmailTemplate::TYPE_UNDER_REVIEW => [
                'label' => 'Application Under Review (on registration)',
                'subject' => 'Application Received',
                'placeholders' => array_merge($common, ['{applicant_name}', '{registration_type_label}']),
            ],
            EmailTemplate::TYPE_APPROVED => [
                'label' => 'Registration Approved (on approval)',
                'subject' => 'Registration Approved',
                'placeholders' => array_merge($common, ['{recipient_name}', '{team_name}']),
            ],
            EmailTemplate::TYPE_WELCOME_CARD => [
                'label' => 'Welcome Card (on approval, with poster attached)',
                'subject' => 'Your Welcome Card!',
                'placeholders' => array_merge($common, ['{player_name}', '{complete_profile_url}']),
            ],
        ];
    }

    public function brandName(): string
    {
        $name = $this->settings->getSetting('email_brand_name');

        return is_string($name) && $name !== '' ? $name : (string) config('app.name', 'Sportzley');
    }

    public function setBrandName(?string $name): void
    {
        $this->settings->updateOrCreateSetting('email_brand_name', $name ?? '');
    }

    /**
     * The raw (unfilled) template the editor loads: tournament override,
     * else global override, else the built-in seed default.
     *
     * @return array{subject:string, body_html:string, source:string}
     */
    public function rawTemplate(string $type, ?Tournament $tournament): array
    {
        if ($tournament && $tournament->exists) {
            $row = EmailTemplate::where('type', $type)
                ->where('tournament_id', $tournament->id)
                ->first();
            if ($row) {
                return ['subject' => $row->subject, 'body_html' => $row->body_html, 'source' => 'tournament'];
            }
        }

        $global = EmailTemplate::where('type', $type)->whereNull('tournament_id')->first();
        if ($global) {
            return ['subject' => $global->subject, 'body_html' => $global->body_html, 'source' => 'global'];
        }

        $default = $this->defaults($type);

        return ['subject' => $default['subject'], 'body_html' => $default['body_html'], 'source' => 'default'];
    }

    /**
     * Resolve a template to its final subject + HTML with placeholders filled.
     *
     * @return array{subject:string, html:string}
     */
    public function resolveVars(string $type, ?Tournament $tournament, array $vars): array
    {
        $raw = $this->rawTemplate($type, $tournament);

        return [
            'subject' => strtr($raw['subject'], $vars),
            'html' => strtr($raw['body_html'], $vars),
        ];
    }

    /**
     * Resolve a template for a tournament/registration/player to its final
     * subject + HTML. Optional $overrides replace specific placeholder values.
     *
     * @return array{subject:string, html:string}
     */
    public function resolve(
        string $type,
        ?Tournament $tournament,
        ?TournamentRegistration $reg = null,
        ?Player $player = null,
        array $overrides = []
    ): array {
        $vars = array_merge($this->data($type, $tournament, $reg, $player), $overrides);

        return $this->resolveVars($type, $tournament, $vars);
    }

    /**
     * Render arbitrary (unsaved) HTML + subject with a tournament's placeholder map.
     *
     * @return array{subject:string, html:string}
     */
    public function renderRaw(string $type, ?Tournament $tournament, string $subject, string $bodyHtml): array
    {
        $vars = $this->data($type, $tournament, null, null);

        return [
            'subject' => strtr($subject, $vars),
            'html' => strtr($bodyHtml, $vars),
        ];
    }

    /**
     * Build the placeholder => value map for a tournament/registration/player.
     * Values that originate from user input are HTML-escaped; colors/urls are raw.
     */
    public function data(string $type, ?Tournament $tournament, ?TournamentRegistration $reg, ?Player $player): array
    {
        $settings = $tournament?->settings;
        $primary = $settings?->primary_color ?: '#1a56db';
        $secondary = $settings?->secondary_color ?: '#ffffff';
        $logo = $settings?->logo_url ?: asset('images/logo/logo.png');

        $player = $player ?: $reg?->player;
        $recipientName = $reg && $reg->type === 'team'
            ? ($reg->captain_name ?? 'Captain')
            : ($player?->name ?? 'Player');

        $startDate = $tournament?->start_date
            ? Carbon::parse($tournament->start_date)->format('d M Y')
            : '';

        $url = ($tournament && $tournament->slug)
            ? route('public.tournament.show', $tournament->slug)
            : url('/');

        return [
            '{brand_name}' => e($this->brandName()),
            '{tournament_name}' => e($tournament?->name ?? 'the tournament'),
            '{tournament_logo}' => $logo,
            '{tournament_start_date}' => e($startDate),
            '{tournament_location}' => e($tournament?->location ?? ''),
            '{tournament_url}' => $url,
            '{primary_color}' => $primary,
            '{secondary_color}' => $secondary,
            '{header_text_color}' => $this->contrastColor($primary),
            '{app_url}' => url('/'),
            '{applicant_name}' => e($recipientName),
            '{recipient_name}' => e($recipientName),
            '{registration_type_label}' => $reg && $reg->type === 'team' ? 'team registration' : 'application',
            '{team_name}' => e($reg?->team_name ?? ''),
            '{player_name}' => e($player?->name ?? 'Player'),
            '{complete_profile_url}' => $url,
        ];
    }

    /** Pick black or white for readable text on the given background hex. */
    public function contrastColor(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Relative luminance (perceptual). < 0.55 → dark bg → white text.
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance < 0.55 ? '#ffffff' : '#111827';
    }

    /**
     * Built-in seed template per type (placeholder-based, contrast-safe).
     *
     * @return array{subject:string, body_html:string}
     */
    public function defaults(string $type): array
    {
        return match ($type) {
            EmailTemplate::TYPE_UNDER_REVIEW => [
                'subject' => 'Application Received - {tournament_name}',
                'body_html' => $this->seedUnderReview(),
            ],
            EmailTemplate::TYPE_APPROVED => [
                'subject' => 'Registration Approved - {tournament_name}',
                'body_html' => $this->seedApproved(),
            ],
            EmailTemplate::TYPE_WELCOME_CARD => [
                'subject' => 'Your {brand_name} Welcome Card!',
                'body_html' => $this->seedWelcome(),
            ],
            default => ['subject' => '', 'body_html' => ''],
        };
    }

    private function seedUnderReview(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: {primary_color}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{tournament_logo}" alt="{tournament_name}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        <h1 style="color: {header_text_color}; margin: 0; font-size: 24px;">Application Received 🎉</h1>
    </div>
    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{applicant_name}</strong>,</p>
        <p style="margin: 0 0 20px 0;">Congratulations — your {registration_type_label} for <strong>{tournament_name}</strong> has been submitted successfully.</p>
        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
            <p style="margin: 0; color: #856404; font-size: 15px;"><strong>You're in the queue.</strong> Your application is now <strong>under review</strong> by the organizers. We'll email you again as soon as it's approved.</p>
        </div>
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {primary_color};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td><td style="padding: 8px 0; font-weight: 600;">{tournament_name}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Start Date:</td><td style="padding: 8px 0;">{tournament_start_date}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Location:</td><td style="padding: 8px 0;">{tournament_location}</td></tr>
            </table>
        </div>
    </div>
    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for registering with {brand_name}</p>
        <p style="margin: 5px 0 0 0;">We'll be in touch soon!</p>
    </div>
</body>
</html>
HTML;
    }

    private function seedApproved(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: {primary_color}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{tournament_logo}" alt="{tournament_name}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        <h1 style="color: {header_text_color}; margin: 0; font-size: 24px;">Registration Approved!</h1>
    </div>
    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{recipient_name}</strong>,</p>
        <p style="margin: 0 0 20px 0;">Great news! Your registration for <strong>{tournament_name}</strong> has been approved.</p>
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {primary_color};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td><td style="padding: 8px 0; font-weight: 600;">{tournament_name}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Start Date:</td><td style="padding: 8px 0;">{tournament_start_date}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Location:</td><td style="padding: 8px 0;">{tournament_location}</td></tr>
            </table>
        </div>
        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">You are now officially part of this tournament. Stay tuned for further updates and match schedules!</p>
        </div>
        <div style="text-align: center;">
            <a href="{tournament_url}" style="display: inline-block; background: {primary_color}; color: {header_text_color}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">View Tournament</a>
        </div>
    </div>
    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for registering with {brand_name}</p>
        <p style="margin: 5px 0 0 0;">Good luck!</p>
    </div>
</body>
</html>
HTML;
    }

    private function seedWelcome(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f7; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; padding: 25px; background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 8px;">
        <div style="text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eeeeee;">
            <img src="{tournament_logo}" alt="{brand_name}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px; border: 1px solid #e9ecef;">
            <h1 style="color: #2c3e50; margin: 0; font-size: 24px;">Welcome Aboard!</h1>
        </div>
        <div style="font-size: 16px;">
            <p>Hi <strong style="color: #0056b3;">{player_name}</strong>,</p>
            <p>A warm welcome to <strong style="color: #0056b3;">{brand_name}</strong>! We are thrilled to have you join our community.</p>
            <p>Your journey to track your performance, join events, and showcase your skills starts now. To get the most out of the platform, we recommend completing your profile.</p>
            <a href="{complete_profile_url}" style="display: block; width: fit-content; margin: 25px auto; padding: 12px 25px; background-color: {primary_color}; color: {header_text_color} !important; text-decoration: none; border-radius: 5px; font-weight: bold;">Complete Your Profile</a>
            <p>If you have any questions, feel free to reply to this email. We're happy to help!</p>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #777;">
            <p>Best regards,</p>
            <p>The {brand_name} Team</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
