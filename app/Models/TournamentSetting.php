<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        // Branding
        'logo',
        'background_image',
        'flyer_image',
        'primary_color',
        'secondary_color',
        // Registration
        'player_registration_open',
        'team_registration_open',
        'registration_deadline',
        'max_players_per_team',
        'min_players_per_team',
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
    ];

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

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
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
