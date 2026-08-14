<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionTemplate extends Model
{
    /** How a template was authored. Orthogonal to `type`, which is which screen. */
    public const RENDER_POSITIONED = 'positioned';
    public const RENDER_HTML = 'html';

    /** Which screen a template describes. */
    public const TYPE_LIVE_DISPLAY = 'live_display';
    public const TYPE_SOLD_DISPLAY = 'sold_display';
    public const TYPE_PLAYER_CARD = 'player_card';

    /**
     * The broadcast lower-third strip.
     *
     * Always authored as HTML: the positioned editor places elements on a 1601x910 card and
     * has nothing to say about a 90px strip, so offering it here would be a dead end.
     */
    public const TYPE_TICKER = 'ticker';

    /**
     * Types the LED wall can render.
     *
     * `player_card` and `live_display` describe the *same* canvas: the positioned editor
     * edits one fixed element set (getElementKeys() — player image, name, role, styles,
     * current bid, sold badge, team logo, highest bidder, stats table), whatever type is
     * selected. So the type was effectively a label, and choosing "Player Card" produced a
     * template the wall silently refused to resolve — the auction fell back to its old
     * background with no indication why.
     *
     * @return list<string>
     */
    public static function wallTypes(): array
    {
        return [self::TYPE_LIVE_DISPLAY, self::TYPE_PLAYER_CARD];
    }

    /**
     * The types a lookup for $type should accept.
     *
     * Only the wall broadens; sold_display and ticker stay exact, because those genuinely
     * describe different screens.
     *
     * @return list<string>
     */
    public static function acceptableTypes(string $type): array
    {
        return $type === self::TYPE_LIVE_DISPLAY ? self::wallTypes() : [$type];
    }

    /** @return array<string, string> value => label, for the type picker. */
    public static function types(): array
    {
        return [
            self::TYPE_LIVE_DISPLAY => 'LED Wall (live display)',
            self::TYPE_TICKER => 'Broadcast Ticker (HTML only)',
            self::TYPE_SOLD_DISPLAY => 'Sold Display',
            self::TYPE_PLAYER_CARD => 'Player Card',
        ];
    }

    protected $fillable = [
        'auction_id',
        'organization_id',
        'name',
        'type',
        'render_mode',
        'html_body',
        'html_css',
        'html_body_previous',
        'html_refresh_ms',
        'html_transparent_bg',
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
        'html_transparent_bg' => 'boolean',
        'html_refresh_ms' => 'integer',
    ];

    /** @return array<string, string> */
    public static function renderModes(): array
    {
        return [
            self::RENDER_POSITIONED => 'Positioned elements (drag & drop)',
            self::RENDER_HTML => 'Raw HTML + CSS',
        ];
    }

    public function isHtmlMode(): bool
    {
        return $this->render_mode === self::RENDER_HTML;
    }

    /** How often the HTML screen re-reads the feed, clamped to something sane. */
    public function htmlRefreshMs(): int
    {
        return max(500, min(60000, (int) ($this->html_refresh_ms ?: 2000)));
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the default template for a specific type
     *
     * Ordered, not just `first()`: two rows can satisfy this and an unordered query
     * picks an arbitrary one, so the same auction could render a different screen
     * after an unrelated insert.
     */
    public static function getDefault(string $type = 'live_display'): ?self
    {
        return static::whereIn('type', static::acceptableTypes($type))
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get template for a specific auction
     */
    public static function forAuction(int $auctionId, string $type = 'live_display'): ?self
    {
        // First try auction-specific template. Prefer one flagged default, then the
        // most recent — anything is better than the arbitrary row an unordered
        // query returns when an auction has more than one active template.
        $template = static::where('auction_id', $auctionId)
            ->whereIn('type', static::acceptableTypes($type))
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        // Fall back to default template
        if (! $template) {
            $template = static::getDefault($type);
        }

        return $template;
    }

    /**
     * The template an auction should render with.
     *
     * Explicit pick first, then a template bound to this auction, then the global
     * default. `forAuction()` stays as the older, id-only entry point.
     */
    public static function resolveFor(Auction $auction, string $type = 'live_display'): ?self
    {
        if ($chosenId = static::chosenIdFor($auction, $type)) {
            $chosen = static::where('id', $chosenId)
                ->whereIn('type', static::acceptableTypes($type))
                ->where('is_active', true)
                ->first();

            if ($chosen) {
                return $chosen;
            }
        }

        return static::forAuction($auction->id, $type);
    }

    /**
     * The column holding this auction's explicit pick for a given screen.
     *
     * The wall and the ticker are two screens running side by side in the same room, so each
     * gets its own column. Reading `auction_template_id` for every type — as this used to —
     * meant a ticker lookup tested the WALL's chosen template against `type = 'ticker'`,
     * always missed, and silently fell through to the default. The explicit choice was
     * therefore impossible to honour for anything but the wall.
     *
     * Types with no column of their own fall straight through to the per-auction/default
     * chain, which is the pre-existing behaviour for sold_display and player_card.
     */
    /**
     * A template this auction is allowed to render, chosen explicitly per screen.
     *
     * One auction drives several physical displays — a 16:9 projector, a portrait LED wall,
     * a broadcast strip — and one stored template cannot serve all of them, because each is
     * designed against a fixed canvas. So each screen may name its own template in the URL
     * and every display keeps the layout drawn for its resolution.
     *
     * These pages are PUBLIC and unauthenticated, so the id cannot simply be trusted: the
     * template must belong to this auction, or to this auction's organization, or be a
     * global one, and it must be the right type for the screen asking. Anything else falls
     * back to the auction's normal choice rather than rendering another org's artwork.
     */
    public static function overrideFor(Auction $auction, string $type, mixed $templateId): ?self
    {
        if (! is_numeric($templateId)) {
            return null;
        }

        return static::where('id', (int) $templateId)
            ->whereIn('type', static::acceptableTypes($type))
            ->where('is_active', true)
            ->where(function ($q) use ($auction) {
                $q->whereNull('organization_id')
                    ->orWhere('auction_id', $auction->id);

                if ($auction->organization_id) {
                    $q->orWhere('organization_id', $auction->organization_id);
                }
            })
            ->first();
    }

    public static function chosenIdFor(Auction $auction, string $type): ?int
    {
        $column = match ($type) {
            self::TYPE_LIVE_DISPLAY => 'auction_template_id',
            self::TYPE_TICKER => 'ticker_template_id',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        return $auction->{$column} ? (int) $auction->{$column} : null;
    }

    /**
     * Templates this user may see: their own organization's, plus the global ones.
     *
     * Not a global scope. `OrganizationScope` filters on strict equality, which would
     * hide every global (NULL) template and break the fallback the LED wall depends on.
     */
    public function scopeVisibleTo($query, $user)
    {
        if (! $user || (method_exists($user, 'hasRole') && $user->hasRole('Superadmin'))) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->whereNull('organization_id');

            if (! empty($user->organization_id)) {
                $q->orWhere('organization_id', $user->organization_id);
            }
        });
    }

    /** May this user modify this template? Global templates are Superadmin-only. */
    public function isEditableBy($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        // A global template is shared by every organization, so editing one is a
        // Superadmin act — an organizer changing it would change everyone's screen.
        if ($this->organization_id === null) {
            return false;
        }

        return ! empty($user->organization_id)
            && (int) $this->organization_id === (int) $user->organization_id;
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
            // base_price is the player's OPENING figure, shown alongside the live one.
            // bid_label is a dynamic caption (BASE VALUE / CURRENT BID / SOLD PRICE) that
            // follows whatever state the player is in, so it cannot label a second figure —
            // hence a element of its own, captioned by its own ::before on the wall.
            'base_price',
            // Travel plan: the player's dates, when they have any. Its own element because an
            // organizer decides where it belongs on the artwork — and because it is absent for
            // most players, so it cannot share a line with something always present.
            'travel_plan',
            /*
             * The club a player currently turns out for — where they come from, as against the
             * team that buys them. The poster has carried this since the auction poster types
             * were added; the wall had no element for it at all, so it could never be shown
             * however the template was drawn.
             */
            'playing_team',
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
            // The leading team's name sat at 470 while the stats table began at 480, so
            // the two always overlapped on a default layout — the team name printed
            // straight through the table header. The name keeps its band and the table
            // starts below it.
            /*
             * Was top 470 / left 570: inside the stats table's own 550..1050 span and only 75px
             * above a block starting at 545, so the highest bidder printed straight across the
             * MATCHES / RUNS / WICKETS row and neither could be read. Moved below the table,
             * which ends at 695, and roughly centred on the 1601-wide canvas.
             */
            /*
             * Under the photo, to the right of the live figure.
             *
             * player_image sits at bottom 305 / left 114 / width 380, so its lower edge is at
             * y 605 on a 910 canvas and x 114..494 is its column. current_bid is at bottom 197
             * (y ~713) / left 234, so this sits beside it at x 400 — clear of the image above,
             * of sold_badge and team_logo below, and of stats_table which starts at x 550.
             */
            'base_price' => ['bottom' => 197, 'left' => 400, 'fontSize' => 26],
            /*
             * Below the stats table, not on top of it.
             *
             * The first default put this at top 470, which is inside the stats block
             * (488-635 on the shipped artwork) — so the element landed on the table in the
             * editor AND on the wall. Same coordinates in both, but the wrong ones.
             */
            'travel_plan' => ['top' => 650, 'left' => 545, 'fontSize' => 24],
            'playing_team' => ['top' => 690, 'left' => 545, 'fontSize' => 24],
            'highest_bidder' => ['top' => 715, 'left' => 600, 'fontSize' => 28],
            'stats_table' => ['top' => 545, 'left' => 550, 'width' => 500, 'height' => 150, 'fontSize' => 20,
                /*
                 * No fills by default. A wall template's background is artwork, and a panel
                 * behind the header plus a tint behind every row stacked two more tinted
                 * layers on top of it — the stats block read as a dirty grey box pasted over
                 * the design. The numbers carry themselves on weight and size; a template
                 * that wants panels back sets headerBg / rowBg / cellBg in the editor.
                 */
                'headerBg' => '', 'headerColor' => '#ffffff',
                'rowBg' => '', 'cellColor' => '#ffffff', 'cellPadding' => 10,
                'tableBorderColor' => 'rgba(255,255,255,0.2)', 'tableBorderWidth' => 0,
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
