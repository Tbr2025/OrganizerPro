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
            '{contact_info}',
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
            EmailTemplate::TYPE_RETAINED_WELCOME_CARD => [
                'label' => 'Retained Welcome Card (on retention, with poster attached)',
                'subject' => 'Welcome to the Team!',
                'placeholders' => array_merge($common, ['{player_name}', '{team_name}', '{retained_value}', '{complete_profile_url}']),
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
        // Main application logo (absolute URL) — used as a reliable fallback so the
        // header logo is never a broken image.
        $appLogo = $this->appLogoUrl();
        $logo = $settings?->logo_url ?: $appLogo;

        // Header logos block: app logo always, tournament logo alongside when it
        // has its own (avoids showing the same logo twice).
        $headerLogos = '<img src="' . $appLogo . '" alt="' . e($this->brandName()) . '" style="height:52px;max-width:150px;object-fit:contain;background:#ffffff;border-radius:8px;padding:6px;vertical-align:middle;">';
        if ($settings?->logo_url) {
            $headerLogos .= '<img src="' . $settings->logo_url . '" alt="' . e($tournament?->name ?? '') . '" style="height:52px;width:52px;object-fit:contain;background:#ffffff;border-radius:50%;padding:5px;vertical-align:middle;margin-left:10px;">';
        }

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

        // Build contact info HTML from tournament settings.
        $contactHtml = '';
        if ($settings) {
            $parts = [];
            if ($settings->contact_email) {
                $parts[] = '<a href="mailto:' . e($settings->contact_email) . '" style="color:' . $primary . ';text-decoration:none;font-weight:600;">' . e($settings->contact_email) . '</a>';
            }
            if ($settings->contact_phone) {
                $parts[] = '<a href="tel:' . e($settings->contact_phone) . '" style="color:' . $primary . ';text-decoration:none;font-weight:600;">' . e($settings->contact_phone) . '</a>';
            }
            if ($settings->whatsapp_contact) {
                $wa = preg_replace('/[^0-9]/', '', $settings->whatsapp_contact);
                $parts[] = '<a href="https://wa.me/' . $wa . '" style="color:#25d366;text-decoration:none;font-weight:600;">WhatsApp</a>';
            }
            if ($parts) {
                $contactHtml = 'Contact us: ' . implode(' &nbsp;|&nbsp; ', $parts);
            }
        }

        return [
            '{brand_name}' => e($this->brandName()),
            '{tournament_name}' => e($tournament?->name ?? 'the tournament'),
            '{tournament_logo}' => $logo,
            '{app_logo}' => $appLogo,
            '{header_logos}' => $headerLogos,
            '{tournament_start_date}' => e($startDate ?: 'To be announced'),
            '{tournament_location}' => e($tournament?->location ?: 'To be announced'),
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
            '{retained_value}' => '',
            '{complete_profile_url}' => route('login'),
            '{contact_info}' => $contactHtml,
        ];
    }

    /** Main application logo as an absolute URL (reliable in email clients). */
    private function appLogoUrl(): string
    {
        $raw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';

        return \Illuminate\Support\Str::startsWith($raw, ['http://', 'https://'])
            ? $raw
            : asset(ltrim($raw, '/'));
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
            EmailTemplate::TYPE_RETAINED_WELCOME_CARD => [
                'subject' => 'Welcome to the Team! - {tournament_name}',
                'body_html' => $this->seedRetainedWelcome(),
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
        <div style="margin: 0 auto 15px;">{header_logos}</div>
        <h1 style="color: {header_text_color}; margin: 0; font-size: 24px;">Application Received</h1>
    </div>
    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{applicant_name}</strong>,</p>
        <p style="margin: 0 0 20px 0;">Congratulations — your {registration_type_label} for <strong>{tournament_name}</strong> has been submitted successfully.</p>
        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
            <p style="margin: 0; color: #856404; font-size: 15px;">Your application is under review. We'll notify you once it's approved.</p>
        </div>
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {primary_color};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td><td style="padding: 8px 0; font-weight: 600;">{tournament_name}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Start Date:</td><td style="padding: 8px 0;">{tournament_start_date}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Location:</td><td style="padding: 8px 0;">{tournament_location}</td></tr>
            </table>
        </div>
        <p style="margin: 0; font-size: 14px; color: #555;">{contact_info}</p>
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
        <div style="margin: 0 auto 15px;">{header_logos}</div>
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
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="{tournament_url}" style="display: inline-block; background: {primary_color}; color: {header_text_color}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">View Tournament</a>
        </div>
        <p style="margin: 0; font-size: 14px; color: #555;">{contact_info}</p>
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
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: {primary_color}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">{header_logos}</div>
        <h1 style="color: {header_text_color}; margin: 0; font-size: 24px;">Welcome Aboard!</h1>
    </div>
    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Hi <strong>{player_name}</strong>,</p>
        <p style="margin: 0 0 20px 0;">Welcome to <strong>{tournament_name}</strong>! We're thrilled to have you on board.</p>
        <p style="margin: 0 0 20px 0;">To get the most out of your experience, complete your profile so organizers and teammates can find you easily.</p>
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="{complete_profile_url}" style="display: inline-block; background: {primary_color}; color: {header_text_color}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">Complete Your Profile</a>
        </div>
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {primary_color};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td><td style="padding: 8px 0; font-weight: 600;">{tournament_name}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Start Date:</td><td style="padding: 8px 0;">{tournament_start_date}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Location:</td><td style="padding: 8px 0;">{tournament_location}</td></tr>
            </table>
        </div>
        <p style="margin: 0; font-size: 14px; color: #555;">{contact_info}</p>
    </div>
    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for joining {brand_name}</p>
        <p style="margin: 5px 0 0 0;">Good luck!</p>
    </div>
</body>
</html>
HTML;
    }

    private function seedRetainedWelcome(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: {primary_color}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">{header_logos}</div>
        <h1 style="color: {header_text_color}; margin: 0; font-size: 24px;">Welcome to the Team!</h1>
    </div>
    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{player_name}</strong>,</p>
        <p style="margin: 0 0 20px 0;">You've been retained by <strong>{team_name}</strong> for <strong>{tournament_name}</strong>!</p>
        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">Retained Value: <strong>{retained_value} Points</strong></p>
        </div>
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {primary_color};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td><td style="padding: 8px 0; font-weight: 600;">{tournament_name}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Start Date:</td><td style="padding: 8px 0;">{tournament_start_date}</td></tr>
                <tr><td style="padding: 8px 0; color: #6c757d;">Location:</td><td style="padding: 8px 0;">{tournament_location}</td></tr>
            </table>
        </div>
        <p style="margin: 0; font-size: 14px; color: #555;">{contact_info}</p>
    </div>
    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for being part of {brand_name}</p>
        <p style="margin: 5px 0 0 0;">Good luck this season!</p>
    </div>
</body>
</html>
HTML;
    }
}
