<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionTemplate extends Model
{
    protected $fillable = [
        'auction_id',
        'name',
        'type',
        'background_image',
        'sold_badge_image',
        'canvas_width',
        'canvas_height',
        'element_positions',
        'player_image_pos',
        'player_name_pos',
        'player_role_pos',
        'batting_style_pos',
        'bowling_style_pos',
        'current_bid_pos',
        'bid_label_pos',
        'stats_matches_pos',
        'stats_runs_pos',
        'stats_wickets_pos',
        'sold_badge_pos',
        'team_logo_pos',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'element_positions' => 'array',
        'player_image_pos' => 'array',
        'player_name_pos' => 'array',
        'player_role_pos' => 'array',
        'batting_style_pos' => 'array',
        'bowling_style_pos' => 'array',
        'current_bid_pos' => 'array',
        'bid_label_pos' => 'array',
        'stats_matches_pos' => 'array',
        'stats_runs_pos' => 'array',
        'stats_wickets_pos' => 'array',
        'sold_badge_pos' => 'array',
        'team_logo_pos' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * Get the default template for a specific type
     */
    public static function getDefault(string $type = 'live_display'): ?self
    {
        return static::where('type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get template for a specific auction
     */
    public static function forAuction(int $auctionId, string $type = 'live_display'): ?self
    {
        // First try auction-specific template
        $template = static::where('auction_id', $auctionId)
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        // Fall back to default template
        if (!$template) {
            $template = static::getDefault($type);
        }

        return $template;
    }

    /**
     * Get background image URL
     */
    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_image
            ? asset('storage/' . $this->background_image)
            : null;
    }

    /**
     * Get sold badge image URL
     */
    public function getSoldBadgeUrlAttribute(): ?string
    {
        return $this->sold_badge_image
            ? asset('storage/' . $this->sold_badge_image)
            : null;
    }

    /**
     * Get default element positions
     */
    public static function getDefaultPositions(): array
    {
        return [
            'player_image' => ['bottom' => 305, 'left' => 114, 'width' => 380],
            'player_name' => ['top' => 210, 'left' => 545, 'fontSize' => 46],
            'player_role' => ['top' => 275, 'left' => 570, 'fontSize' => 24],
            'batting_style' => ['top' => 334, 'left' => 570, 'fontSize' => 34],
            'bowling_style' => ['top' => 404, 'left' => 570, 'fontSize' => 34],
            'current_bid' => ['bottom' => 197, 'left' => 234, 'fontSize' => 32],
            'bid_label' => ['bottom' => 243, 'left' => 186, 'fontSize' => 32],
            'stats_matches_label' => ['top' => 490, 'left' => 600, 'fontSize' => 33],
            'stats_matches_value' => ['top' => 550, 'left' => 605, 'fontSize' => 33],
            'stats_wickets_label' => ['top' => 490, 'left' => 825, 'fontSize' => 33],
            'stats_wickets_value' => ['top' => 550, 'left' => 825, 'fontSize' => 33],
            'stats_runs_label' => ['top' => 490, 'left' => 1020, 'fontSize' => 33],
            'stats_runs_value' => ['top' => 550, 'left' => 1050, 'fontSize' => 33],
            'sold_badge' => ['bottom' => 27, 'left' => 112, 'width' => 150, 'height' => 150],
            'team_logo' => ['bottom' => 56, 'left' => 316, 'width' => 170, 'height' => 100],
        ];
    }
}
