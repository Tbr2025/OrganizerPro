<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentBanner extends Model
{
    public const PAGE_REGISTRATION = 'registration';
    public const PAGE_PLAYER_DASHBOARD = 'player_dashboard';
    public const PAGE_TEAM_MANAGER_DASHBOARD = 'team_manager_dashboard';

    public const PAGES = [
        self::PAGE_REGISTRATION => 'Player Registration',
        self::PAGE_PLAYER_DASHBOARD => 'Player Dashboard',
        self::PAGE_TEAM_MANAGER_DASHBOARD => 'Team Manager Dashboard',
    ];

    public const POSITION_TOP = 'top';
    public const POSITION_BOTTOM = 'bottom';

    public const POSITIONS = [
        self::POSITION_TOP => 'Top',
        self::POSITION_BOTTOM => 'Bottom',
    ];

    public const DISPLAY_STATIC = 'static';
    public const DISPLAY_SLIDER = 'slider';

    public const DISPLAY_TYPES = [
        self::DISPLAY_STATIC => 'Static (Single Image)',
        self::DISPLAY_SLIDER => 'Slider (Auto-Rotate)',
    ];

    public const RATIO_WIDE = 'wide';
    public const RATIO_LANDSCAPE = 'landscape';
    public const RATIO_PORTRAIT = 'portrait';
    public const RATIO_SQUARE = 'square';

    public const ASPECT_RATIOS = [
        self::RATIO_WIDE => 'Wide Banner (728×90)',
        self::RATIO_LANDSCAPE => 'Landscape (16:9)',
        self::RATIO_PORTRAIT => 'Portrait',
        self::RATIO_SQUARE => 'Square (1:1)',
    ];

    protected $fillable = [
        'tournament_id',
        'page',
        'position',
        'display_type',
        'image_path',
        'aspect_ratio',
        'link_url',
        'alt_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function scopeForSlot($query, $tournamentId, $page, $position)
    {
        return $query->where('tournament_id', $tournamentId)
            ->where('page', $page)
            ->where('position', $position)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
}
