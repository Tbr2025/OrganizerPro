<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved setup: kind 'wall' (the LED wall's timings, rotation, theme) or
 * 'ticker' (a live-video ticker design, see TickerDesign). Platform presets
 * (organization_id null) come from Super Admin and are offered to every
 * organization; the rest belong to one.
 */
class LedPreset extends Model
{
    public const KINDS = ['wall', 'ticker'];

    protected $fillable = ['organization_id', 'kind', 'name', 'settings', 'is_default', 'created_by'];

    protected $attributes = ['kind' => 'wall'];

    protected $casts = ['settings' => 'array', 'is_default' => 'boolean'];

    public function scopeWall(Builder $q): Builder
    {
        return $q->where('kind', 'wall');
    }

    public function scopeTicker(Builder $q): Builder
    {
        return $q->where('kind', 'ticker');
    }

    /** Platform presets plus this organization's own. */
    public function scopeAvailableTo(Builder $q, ?int $organizationId): Builder
    {
        return $q->where(fn ($q) => $q->whereNull('organization_id')->when($organizationId, fn ($q) => $q->orWhere('organization_id', $organizationId)))
            ->orderByRaw('organization_id IS NULL')->orderBy('name');
    }

    public function isPlatform(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * Settings for a new wall: the tournament's last wall, else the
     * organization's default preset, else the platform default, else built-in.
     */
    public static function startingSettings(Tournament $tournament): array
    {
        $previous = LedDisplay::where('tournament_id', $tournament->id)->latest('id')->value('settings');
        if (is_string($previous)) {
            $previous = json_decode($previous, true);
        }
        $preset = $previous ? null : (
            self::wall()->where('organization_id', $tournament->organization_id)->where('is_default', true)->first()
            ?? self::wall()->whereNull('organization_id')->where('is_default', true)->first()
        );
        $settings = array_merge(LedDisplay::DEFAULT_SETTINGS, $previous ?: (array_diff_key($preset?->settings ?? [], ['ticker' => 1])));

        // The ticker: the tournament's last design, else the default ticker template.
        $settings['ticker'] = \App\Services\LedWall\TickerDesign::normalize(
            ($previous['ticker'] ?? null) ?: \App\Services\LedWall\TickerDesign::defaultTemplate($tournament->organization_id)
        );

        return $settings;
    }
}
