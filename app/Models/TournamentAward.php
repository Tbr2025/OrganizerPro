<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TournamentAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'slug',
        'icon',
        'is_match_level',
        'is_active',
        'order',
        'template_image',
        'template_settings',
    ];

    protected $casts = [
        'is_match_level' => 'boolean',
        'is_active' => 'boolean',
        'template_settings' => 'array',
    ];

    /**
     * Get default template settings for an award type
     */
    public static function getDefaultTemplateSettings(string $awardName = 'default'): array
    {
        $defaults = [
            'canvas' => ['width' => 1080, 'height' => 1350],
            'background' => [
                'color' => '#1a1a2e',
                'gradient' => ['from' => '#1a1a2e', 'to' => '#16213e', 'direction' => '135deg'],
            ],
            'player_image' => [
                'x' => 390, 'y' => 300, 'width' => 300, 'height' => 300,
                'radius' => 150, 'border_width' => 6, 'border_color' => '#fbbf24',
            ],
            'player_name' => [
                'x' => 540, 'y' => 680, 'font_size' => 48, 'font_color' => '#ffffff',
                'font_weight' => 'bold', 'text_align' => 'center',
            ],
            'award_name' => [
                'x' => 540, 'y' => 180, 'font_size' => 42, 'font_color' => '#fbbf24',
                'font_weight' => 'bold', 'text_align' => 'center',
            ],
            'award_icon' => [
                'x' => 540, 'y' => 100, 'font_size' => 64,
                'width' => 80, 'height' => 80, 'type' => 'emoji',
            ],
            'team_name' => [
                'x' => 540, 'y' => 750, 'font_size' => 28, 'font_color' => '#9ca3af',
                'text_align' => 'center',
            ],
            'match_info' => [
                'x' => 540, 'y' => 1200, 'font_size' => 24, 'font_color' => '#6b7280',
                'text_align' => 'center',
            ],
            'tournament_logo' => [
                'x' => 490, 'y' => 1250, 'width' => 100, 'height' => 100,
            ],
        ];

        // Award-specific customizations
        $awardSpecific = [
            'man of the match' => [
                'background' => ['gradient' => ['from' => '#4c1d95', 'to' => '#7c3aed', 'direction' => '135deg']],
                'player_image' => ['border_color' => '#fbbf24'],
            ],
            'best batsman' => [
                'background' => ['gradient' => ['from' => '#065f46', 'to' => '#059669', 'direction' => '135deg']],
                'player_image' => ['border_color' => '#34d399'],
            ],
            'best bowler' => [
                'background' => ['gradient' => ['from' => '#991b1b', 'to' => '#dc2626', 'direction' => '135deg']],
                'player_image' => ['border_color' => '#f87171'],
            ],
            'best fielder' => [
                'background' => ['gradient' => ['from' => '#1e40af', 'to' => '#3b82f6', 'direction' => '135deg']],
                'player_image' => ['border_color' => '#60a5fa'],
            ],
            'best catch' => [
                'background' => ['gradient' => ['from' => '#92400e', 'to' => '#d97706', 'direction' => '135deg']],
                'player_image' => ['border_color' => '#fbbf24'],
            ],
        ];

        $key = strtolower($awardName);
        if (isset($awardSpecific[$key])) {
            return array_replace_recursive($defaults, $awardSpecific[$key]);
        }

        return $defaults;
    }

    /**
     * Get merged template settings (user settings + defaults)
     */
    public function getMergedTemplateSettings(): array
    {
        $defaults = self::getDefaultTemplateSettings($this->name);
        $userSettings = $this->template_settings ?? [];

        return array_replace_recursive($defaults, $userSettings);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($award) {
            if (empty($award->slug)) {
                $award->slug = Str::slug($award->name);
            }
        });
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matchAwards(): HasMany
    {
        return $this->hasMany(MatchAward::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMatchLevel($query)
    {
        return $query->where('is_match_level', true);
    }

    public function scopeTournamentLevel($query)
    {
        return $query->where('is_match_level', false);
    }

    public function getTemplateImageUrlAttribute(): ?string
    {
        return $this->template_image ? asset('storage/' . $this->template_image) : null;
    }
}
