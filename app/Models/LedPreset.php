<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved LED wall setup. Platform presets (organization_id null) come from
 * Super Admin and are offered to every organization; the rest belong to one.
 */
class LedPreset extends Model
{
    protected $fillable = ['organization_id', 'name', 'settings', 'is_default', 'created_by'];

    protected $casts = ['settings' => 'array', 'is_default' => 'boolean'];

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
            self::where('organization_id', $tournament->organization_id)->where('is_default', true)->first()
            ?? self::whereNull('organization_id')->where('is_default', true)->first()
        );

        return array_merge(LedDisplay::DEFAULT_SETTINGS, $previous ?: ($preset?->settings ?? []));
    }
}
