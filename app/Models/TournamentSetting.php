<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentSetting extends Model
{
    use HasFactory;

    public const STATUSES = [
        'open' => ['label' => 'Open', 'color' => 'green', 'message' => 'Registration is open'],
        'paused' => ['label' => 'Paused', 'color' => 'yellow', 'message' => 'Registration is temporarily paused'],
        'pending' => ['label' => 'Coming Soon', 'color' => 'blue', 'message' => 'Registration opening soon'],
        'draft' => ['label' => 'Draft', 'color' => 'gray', 'message' => 'Tournament details coming soon'],
        'closed' => ['label' => 'Closed', 'color' => 'red', 'message' => 'Registration is closed'],
        'completed' => ['label' => 'Completed', 'color' => 'gray', 'message' => 'Tournament has been completed'],
    ];

    public const REGISTRATION_STATUSES = [
        'open' => ['label' => 'Open', 'color' => 'green', 'message' => 'Registration is open'],
        'paused' => ['label' => 'Paused', 'color' => 'yellow', 'message' => 'Registration is temporarily paused'],
        'coming_soon' => ['label' => 'Coming Soon', 'color' => 'blue', 'message' => 'Registration opening soon'],
        'closed' => ['label' => 'Closed', 'color' => 'red', 'message' => 'Registration is closed'],
    ];

    protected $fillable = [
        'tournament_id',
        'tournament_status',
        'player_registration_status',
        'team_registration_status',
        // Branding
        'logo',
        'background_image',
        'flyer_image',
        'primary_color',
        'secondary_color',
        'accent_color',
        // Registration
        'player_registration_open',
        'team_registration_open',
        'registration_deadline',
        'max_players_per_team',
        'min_players_per_team',
        'default_country',
        'min_age',
        'max_age',
        // Fixture Settings
        'format',
        'number_of_groups',
        'teams_per_group',
        'matches_per_week',
        'number_of_grounds',
        'has_quarter_finals',
        'has_semi_finals',
        'has_third_place',
        'overs_per_match',
        // Points
        'points_per_win',
        'points_per_tie',
        'points_per_no_result',
        'points_per_loss',
        // Notifications
        'match_poster_days_before',
        'send_match_reminders',
        'send_result_notifications',
        'auto_send_welcome_cards',
        'auto_send_flyer_on_registration',
        'auto_send_match_summary',
        'summary_update_mode',
        // Template references
        'default_welcome_template_id',
        'default_match_template_id',
        'default_summary_template_id',
        'semi_final_template_id',
        'final_template_id',
        // Social
        'description',
        'rules',
        'social_links',
        'contact_email',
        'contact_phone',
        'whatsapp_contact',
        // Calendar scheduling
        'available_days',
        'default_time_slots',
        // Registration form fields config
        'registration_form_fields',
        'team_registration_form_fields',
        'registration_theme',
        // Terms & Conditions
        'terms_and_conditions_content',
        'team_terms_and_conditions_content',
        // Player photo guidelines (registration)
        'photo_guidelines',
        'photo_sample_path',
        // Team image/logo guidelines (registration)
        'team_photo_guidelines',
        'team_photo_sample_path',
        // Social share / Open Graph image
        'og_image',
    ];

    protected $casts = [
        'player_registration_open' => 'boolean',
        'team_registration_open' => 'boolean',
        'registration_deadline' => 'datetime',
        'has_quarter_finals' => 'boolean',
        'has_semi_finals' => 'boolean',
        'has_third_place' => 'boolean',
        'send_match_reminders' => 'boolean',
        'send_result_notifications' => 'boolean',
        'auto_send_welcome_cards' => 'boolean',
        'auto_send_flyer_on_registration' => 'boolean',
        'auto_send_match_summary' => 'boolean',
        'social_links' => 'array',
        'available_days' => 'array',
        'default_time_slots' => 'array',
        'registration_form_fields' => 'array',
        'team_registration_form_fields' => 'array',
        'registration_theme' => 'array',
    ];

    /**
     * Registration-page theme merged over sensible defaults (derived from the
     * tournament's colours so an un-themed page looks like the current design).
     */
    public function registrationTheme(): array
    {
        $accent = $this->accent_color ?: '#fbbf24';
        $primary = $this->primary_color ?: '#1a1a2e';
        $secondary = $this->secondary_color ?: '#16213e';

        $defaults = [
            'banner_image' => null,
            'banner_title' => null,
            'banner_subtitle' => null,
            'header_gradient_from' => $accent,
            'header_gradient_to' => $accent,
            'icon_color' => $accent,
            'label_color' => '#cbd5e1',
            'page_bg_from' => $primary,
            'page_bg_to' => $secondary,
            'card_bg' => 'rgba(255,255,255,0.04)',
            'footer_gradient_from' => $primary,
            'footer_gradient_to' => $secondary,
            'button_gradient_from' => $accent,
            'button_gradient_to' => $accent,
        ];

        $saved = is_array($this->registration_theme) ? $this->registration_theme : [];
        foreach ($saved as $key => $value) {
            if (array_key_exists($key, $defaults) && $value !== null && $value !== '') {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function isRegistrationOpen(): bool
    {
        if (!$this->registration_deadline) {
            return $this->player_registration_open || $this->team_registration_open;
        }

        return ($this->player_registration_open || $this->team_registration_open)
            && $this->registration_deadline->isFuture();
    }

    public function getTournamentStatusLabel(): string
    {
        return self::STATUSES[$this->tournament_status ?? 'open']['label'] ?? 'Open';
    }

    public function getTournamentStatusColor(): string
    {
        return self::STATUSES[$this->tournament_status ?? 'open']['color'] ?? 'green';
    }

    public function getTournamentStatusMessage(): string
    {
        return self::STATUSES[$this->tournament_status ?? 'open']['message'] ?? 'Registration is open';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getPhotoSampleUrlAttribute(): ?string
    {
        return $this->photo_sample_path ? asset('storage/' . $this->photo_sample_path) : null;
    }

    public function getTeamPhotoSampleUrlAttribute(): ?string
    {
        return $this->team_photo_sample_path ? asset('storage/' . $this->team_photo_sample_path) : null;
    }

    /**
     * Absolute URL of the social-share (Open Graph) image. Falls back to the
     * registration banner → background → logo, so shared links always have a thumbnail.
     * asset() prepends APP_URL so social scrapers get an absolute https URL.
     */
    public function getShareImageUrlAttribute(): ?string
    {
        $banner = is_array($this->registration_theme) ? ($this->registration_theme['banner_image'] ?? null) : null;
        $path = $this->og_image ?: ($banner ?: ($this->background_image ?: $this->logo));

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? asset('storage/' . $this->background_image) : null;
    }

    public function getFlyerImageUrlAttribute(): ?string
    {
        return $this->flyer_image ? asset('storage/' . $this->flyer_image) : null;
    }

    public function getWhatsAppShareLinkAttribute(): ?string
    {
        if (!$this->whatsapp_contact) {
            return null;
        }
        $phone = preg_replace('/[^0-9]/', '', $this->whatsapp_contact);
        return "https://wa.me/{$phone}";
    }

    public function shouldAutoSendWelcomeCards(): bool
    {
        return $this->auto_send_welcome_cards ?? true;
    }

    public function shouldAutoSendFlyer(): bool
    {
        return $this->auto_send_flyer_on_registration ?? true;
    }

    public function shouldAutoSendMatchSummary(): bool
    {
        return $this->auto_send_match_summary ?? true;
    }
}
