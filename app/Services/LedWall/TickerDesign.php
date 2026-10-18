<?php

declare(strict_types=1);

namespace App\Services\LedWall;

use App\Models\LedDisplay;
use App\Models\LedPreset;
use App\Models\Matches;
use App\Models\TournamentSetting;

/**
 * How the live-video score ticker (/live/{match}) looks: colours, size, where
 * it sits on the 1920×1080 frame, and which parts show.
 *
 * A match's design lives on its LED display (settings.ticker), so "use for every
 * match in this tournament" and "new matches start from the tournament's last
 * setup" work for it as they do for the wall. Saved designs are LED presets of
 * kind 'ticker'. With no display yet, the organization's default ticker
 * template applies, else the platform's, else DEFAULTS — today's look.
 */
class TickerDesign
{
    public const DEFAULTS = [
        // Where: top or bottom of the frame; stretched full width or a fixed width
        // aligned left, centre or right; pixels in from the edges.
        'position' => 'bottom',
        'align' => 'stretch',
        'width' => 1400,
        'offset_x' => 24,
        'offset_y' => 24,
        // Size, as a percentage of the standard strip.
        'scale' => 100,
        'radius' => 16,
        'opacity' => 95,
        // Colours.
        'bg' => '#0a0a1e',
        'panel_from' => '#1a1a2e',
        'panel_to' => '#0f3460',
        'accent_from' => '#f7971e',
        'accent_to' => '#ffd200',
        'highlight' => '#facc15',
        'text' => '#ffffff',
        // Team boxes in each team's own colours, or the panel colours.
        'team_colours' => true,
        // The tournament badge in the corner: right, left or hidden.
        'badge' => 'right',
        // Parts of the strip.
        'show_stats' => true,
        'show_this_over' => true,
        'show_players' => true,
        'show_partnership' => true,
        'show_bowler' => true,
        'show_status' => true,
        // LED console moments (wicket, boundary, cards…) above the strip.
        'show_moments' => true,
    ];

    public const COLOURS = ['bg', 'panel_from', 'panel_to', 'accent_from', 'accent_to', 'highlight', 'text'];

    public const TOGGLES = ['team_colours', 'show_stats', 'show_this_over', 'show_players', 'show_partnership', 'show_bowler', 'show_status', 'show_moments'];

    /** Validation rules for a design posted from the console. */
    public static function rules(string $prefix = ''): array
    {
        $rules = [
            'position' => 'required|in:top,bottom',
            'align' => 'required|in:stretch,left,center,right',
            'width' => 'required|integer|min:600|max:1920',
            'offset_x' => 'required|integer|min:0|max:400',
            'offset_y' => 'required|integer|min:0|max:400',
            'scale' => 'required|integer|min:50|max:150',
            'radius' => 'required|integer|min:0|max:40',
            'opacity' => 'required|integer|min:40|max:100',
            'badge' => 'required|in:right,left,hidden',
        ];
        foreach (self::COLOURS as $c) {
            $rules[$c] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        foreach (self::TOGGLES as $t) {
            $rules[$t] = 'boolean';
        }

        return collect($rules)->mapWithKeys(fn ($r, $k) => [$prefix . $k => $r])->all();
    }

    /** Every key present and of the right type; unknown keys dropped. */
    public static function normalize(?array $design): array
    {
        $d = array_merge(self::DEFAULTS, array_intersect_key($design ?? [], self::DEFAULTS));
        foreach (self::TOGGLES as $t) {
            $d[$t] = filter_var($d[$t], FILTER_VALIDATE_BOOLEAN);
        }
        foreach (['width', 'offset_x', 'offset_y', 'scale', 'radius', 'opacity'] as $n) {
            $d[$n] = (int) $d[$n];
        }
        foreach (self::COLOURS as $c) {
            $d[$c] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $d[$c]) ? strtolower($d[$c]) : self::DEFAULTS[$c];
        }

        return $d;
    }

    /** The design this match's ticker shows now. */
    public static function forMatch(Matches $match): array
    {
        $display = LedDisplay::where('match_id', $match->id)->whereNull('revoked_at')->oldest()->first();
        $own = $display ? (($display->settings ?? [])['ticker'] ?? null) : null;

        return self::normalize($own ?: self::defaultTemplate($match->tournament?->organization_id) ?: self::fromTournament($match->tournament_id));
    }

    /**
     * Map a tournament's branding colours to ticker design keys.
     * Returns null when the tournament has no colours set.
     */
    private static function fromTournament(?int $tournamentId): ?array
    {
        if (! $tournamentId) {
            return null;
        }

        $settings = TournamentSetting::where('tournament_id', $tournamentId)->first();
        if (! $settings) {
            return null;
        }

        $primary = $settings->primary_color;
        $secondary = $settings->secondary_color;
        $accent = $settings->accent_color;

        if (! $primary && ! $secondary && ! $accent) {
            return null;
        }

        $design = [];
        if ($primary) {
            $design['bg'] = $primary;
            $design['panel_from'] = $primary;
        }
        if ($secondary) {
            $design['panel_to'] = $secondary;
        }
        if ($accent) {
            $design['accent_from'] = $accent;
            $design['accent_to'] = $accent;
            $design['highlight'] = $accent;
        }

        return $design;
    }

    /** The organization's default ticker template, else the platform's. */
    public static function defaultTemplate(?int $organizationId): ?array
    {
        $preset = LedPreset::ticker()->where('is_default', true)
            ->where(fn ($q) => $q->whereNull('organization_id')->when($organizationId, fn ($q) => $q->orWhere('organization_id', $organizationId)))
            ->orderByRaw('organization_id IS NULL')->first();

        return $preset?->settings;
    }
}
