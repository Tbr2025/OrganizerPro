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
        'unsold_badge_image',
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
     * Get unsold badge image URL
     */
    public function getUnsoldBadgeUrlAttribute(): ?string
    {
        return $this->unsold_badge_image
            ? asset('storage/' . $this->unsold_badge_image)
            : null;
    }

    /**
     * Get all standard element keys
     */
    public static function getElementKeys(): array
    {
        return [
            'player_image', 'player_name', 'player_role',
            'batting_style', 'bowling_style', 'current_bid', 'bid_label',
            'sold_badge', 'team_logo', 'highest_bidder', 'stats_table',
        ];
    }

    /**
     * Get default styling properties for elements
     */
    public static function getDefaultStyling(): array
    {
        return [
            'color' => '#ffffff',
            'bgColor' => '',
            'opacity' => 1,
            'bgOpacity' => 1,
            'borderRadius' => 0,
            'boxShadow' => 'none',
            'textShadow' => 'none',
            'zIndex' => 10,
            'visible' => true,
            'fontWeight' => 'bold',
            'padding' => 0,
            'margin' => 0,
            'letterSpacing' => 0,
            'lineHeight' => '',
            'textAlign' => 'left',
            'textTransform' => 'none',
            'rotation' => 0,
            'borderStyle' => 'none',
            'borderColor' => '',
            'borderWidth' => 0,
            'width' => '',
            'height' => '',
        ];
    }

    /**
     * Get default element positions
     */
    public static function getDefaultPositions(): array
    {
        $styling = static::getDefaultStyling();

        $positions = [
            'player_image' => ['bottom' => 305, 'left' => 114, 'width' => 380],
            'player_name' => ['top' => 210, 'left' => 545, 'fontSize' => 46],
            'player_role' => ['top' => 275, 'left' => 570, 'fontSize' => 24],
            'batting_style' => ['top' => 334, 'left' => 570, 'fontSize' => 34],
            'bowling_style' => ['top' => 404, 'left' => 570, 'fontSize' => 34],
            'current_bid' => ['bottom' => 197, 'left' => 234, 'fontSize' => 32],
            'bid_label' => ['bottom' => 243, 'left' => 186, 'fontSize' => 32],
            'sold_badge' => ['bottom' => 27, 'left' => 112, 'width' => 150, 'height' => 150],
            'team_logo' => ['bottom' => 56, 'left' => 316, 'width' => 170, 'height' => 100],
            'highest_bidder' => ['top' => 470, 'left' => 570, 'fontSize' => 28],
            'stats_table' => ['top' => 480, 'left' => 550, 'width' => 500, 'height' => 150, 'fontSize' => 20,
                'headerBg' => 'rgba(0,0,0,0.7)', 'headerColor' => '#ffffff',
                'rowBg' => 'rgba(255,255,255,0.1)', 'cellColor' => '#ffffff', 'cellPadding' => 10,
                'tableBorderColor' => 'rgba(255,255,255,0.2)', 'tableBorderWidth' => 1,
                'tableColumns' => json_encode([
                    ['label' => 'Matches', 'field' => 'total_matches', 'cellBg' => '', 'cellColor' => '', 'headerBg' => '', 'headerColor' => '', 'width' => ''],
                    ['label' => 'Runs', 'field' => 'total_runs', 'cellBg' => '', 'cellColor' => '', 'headerBg' => '', 'headerColor' => '', 'width' => ''],
                    ['label' => 'Wickets', 'field' => 'total_wickets', 'cellBg' => '', 'cellColor' => '', 'headerBg' => '', 'headerColor' => '', 'width' => ''],
                ])],
        ];

        // Merge styling defaults into each element
        foreach ($positions as $key => &$pos) {
            $pos = array_merge($styling, $pos);
        }

        return $positions;
    }
}
