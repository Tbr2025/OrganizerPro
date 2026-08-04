<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class Auction extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'name',
        'organization_id',
        'tournament_id',
        'start_at',
        'end_at',
        'status',
        'base_price',
        'max_bid_per_player',
        'max_budget_per_team',
        'min_squad_size',
        'min_price_per_player',
        'amount_unit',
        'amount_unit_label',
        'notifications_enabled',
        'email_test_mode',
        'email_dispatch',
        'emails_flushed_at',
        'bid_rules',
        'quick_bid_steps',
        'bid_type',
        'bid_timer_seconds',
        'bid_timer_reset_seconds',
        'timer_enabled',
        'timer_expiry_action',
        'timer_started_at',
        'final_call_enabled',
        'final_call_interval_seconds',
        'open_bid_mode',
        'online_bid_limit_from',
        'online_bid_limit_to',
        'mode_manually_overridden',
        'closed_bid_starts_at',
        'background_image',
        'auction_logo',
        'waiting_background_image',
        'primary_color',
        'secondary_color',
    ];
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'bid_rules' => 'array',
        'bid_timer_seconds' => 'integer',
        'bid_timer_reset_seconds' => 'integer',
        'online_bid_limit_from' => 'decimal:2',
        'online_bid_limit_to' => 'decimal:2',
        'mode_manually_overridden' => 'boolean',
        'closed_bid_starts_at' => 'decimal:2',
        'min_squad_size' => 'integer',
        'min_price_per_player' => 'decimal:2',
        'quick_bid_steps' => 'array',
        'timer_enabled' => 'boolean',
        'timer_started_at' => 'datetime',
        'final_call_enabled' => 'boolean',
        'final_call_interval_seconds' => 'integer',
        'notifications_enabled' => 'boolean',
        'email_test_mode' => 'boolean',
        'emails_flushed_at' => 'datetime',
    ];

    /** When auction mail goes out. */
    public const EMAIL_IMMEDIATE = 'immediate';
    public const EMAIL_DEFERRED = 'deferred';

    /** Number of closing calls before the hammer (first, second, final). */
    public const FINAL_CALL_COUNT = 3;

    /*
    |--------------------------------------------------------------------------
    | Amount unit
    |--------------------------------------------------------------------------
    | What the money is called. Amounts always read on the K / M / B ladder; this
    | only decides the label and whether it sits before or after the figure.
    */

    public const UNIT_POINTS = 'points';
    public const UNIT_COINS = 'coins';
    public const UNIT_USD = 'usd';
    public const UNIT_CUSTOM = 'custom';

    /** @return array<string, string> value => human label, for the settings dropdown. */
    public static function amountUnitOptions(): array
    {
        return [
            self::UNIT_POINTS => 'Points',
            self::UNIT_COINS => 'Coins',
            self::UNIT_USD => 'Dollars ($)',
            self::UNIT_CUSTOM => 'Custom…',
        ];
    }

    /** The label shown next to a figure, e.g. "Points", "Coins", "$", "Credits". */
    public function amountUnitLabel(): string
    {
        return match ($this->amount_unit) {
            self::UNIT_COINS => 'Coins',
            self::UNIT_USD => '$',
            self::UNIT_CUSTOM => trim((string) $this->amount_unit_label) ?: 'Points',
            default => 'Points',
        };
    }

    /** Currency-style units read before the number ($10M); named units read after (10M Points). */
    public function amountUnitIsPrefix(): bool
    {
        return $this->amount_unit === self::UNIT_USD;
    }

    /**
     * A figure with its unit, on the K/M/B ladder — the one place that decides how
     * auction money reads.
     */
    public function formatAmount(int|float|string|null $value, string $placeholder = '—'): string
    {
        $figure = format_points($value, $placeholder);

        if ($figure === $placeholder) {
            return $figure;
        }

        return $this->amountUnitIsPrefix()
            ? $this->amountUnitLabel() . $figure
            : $figure . ' ' . $this->amountUnitLabel();
    }

    /**
     * Unit settings for the client-side formatters, so every screen — including the
     * standalone public displays — renders amounts identically.
     *
     * @return array{label: string, prefix: bool}
     */
    public function amountUnitConfig(): array
    {
        return [
            'label' => $this->amountUnitLabel(),
            'prefix' => $this->amountUnitIsPrefix(),
        ];
    }

    /** Default squad size used when the auction has no explicit rule. */
    public const DEFAULT_MIN_SQUAD_SIZE = 11;

    /** What happens when the bid timer reaches zero. */
    public const TIMER_AUTO_SELL = 'auto_sell';
    public const TIMER_MANUAL = 'manual';

    /**
     * Whether the countdown is enforced for the current mode.
     *
     * Online bidding needs a timer — it is what stops a round running forever when
     * teams stall. Offline mode is the organizer calling the room by hand, so the
     * clock is optional there.
     */
    public function timerApplies(): bool
    {
        if ($this->isOnlineMode()) {
            return true;
        }

        return (bool) ($this->timer_enabled ?? true);
    }

    public function timerAutoSells(): bool
    {
        return $this->timer_expiry_action === self::TIMER_AUTO_SELL;
    }

    /**
     * How long the current clock runs for.
     *
     * A fresh player gets the full `bid_timer_seconds`; each subsequent raise gets the
     * shorter `bid_timer_reset_seconds`, which is what those two columns were always
     * meant to express.
     */
    public function timerLimitSeconds(bool $afterBid = false): int
    {
        if ($afterBid) {
            $reset = (int) ($this->bid_timer_reset_seconds ?: 0);
            if ($reset > 0) {
                return $reset;
            }
        }

        return (int) ($this->bid_timer_seconds ?: 30);
    }

    /**
     * Seconds left on the clock, or null when no clock is running.
     *
     * @param  bool  $afterBid  True once the player on the block has a standing bid.
     */
    public function timerSecondsRemaining(bool $afterBid = false): ?int
    {
        if (! $this->timerApplies() || $this->timer_started_at === null) {
            return null;
        }

        // Integer timestamps, not diffInSeconds(): that returns a float in Carbon 3, so
        // a fraction of a second elapsed (the column stores whole seconds, the clock
        // does not) silently ate a full second off every countdown.
        $elapsed = max(0, now()->getTimestamp() - $this->timer_started_at->getTimestamp());

        return max(0, $this->timerLimitSeconds($afterBid) - $elapsed);
    }

    public function timerHasExpired(bool $afterBid = false): bool
    {
        $remaining = $this->timerSecondsRemaining($afterBid);

        return $remaining !== null && $remaining <= 0;
    }

    /**
     * Clock state for a given player, resolving the correct limit from whether the
     * player already has a bid.
     *
     * @return array{applies: bool, limit: int, remaining: int|null, expired: bool, after_bid: bool, final_call: array|null, final_call_stages: list<array{at: int, stage: int, label: string, is_final: bool}>}
     */
    public function timerStateFor(?AuctionPlayer $auctionPlayer): array
    {
        $afterBid = $auctionPlayer?->current_bid_team_id !== null;
        $remaining = $this->timerSecondsRemaining($afterBid);

        return [
            'applies' => $this->timerApplies(),
            'limit' => $this->timerLimitSeconds($afterBid),
            'remaining' => $remaining,
            'expired' => $this->timerHasExpired($afterBid),
            'after_bid' => $afterBid,
            'final_call' => $this->finalCallFor($remaining),
            // Shipped with every payload so each screen — including the public
            // displays, which are standalone documents and cannot import the admin
            // bundle — derives the same call from the same thresholds instead of
            // re-implementing the rule.
            'final_call_stages' => $this->finalCallStages(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Closing calls
    |--------------------------------------------------------------------------
    | "Going once, going twice, sold": in the closing seconds the display escalates
    | through three calls, spaced `final_call_interval_seconds` apart, and then the
    | configured expiry action resolves the player.
    */

    public function finalCallInterval(): int
    {
        return max(1, (int) ($this->final_call_interval_seconds ?: 3));
    }

    public function finalCallApplies(): bool
    {
        return $this->timerApplies() && (bool) ($this->final_call_enabled ?? true);
    }

    /**
     * The seconds-remaining thresholds at which each call fires, latest first.
     *
     * With the default 3-second interval: FINAL at 3s, SECOND at 6s, FIRST at 9s —
     * i.e. the last ten seconds of the clock.
     *
     * @return list<array{at: int, stage: int, label: string, is_final: bool}>
     */
    public function finalCallStages(): array
    {
        if (! $this->finalCallApplies()) {
            return [];
        }

        $interval = $this->finalCallInterval();
        $labels = [1 => 'FIRST CALL', 2 => 'SECOND CALL', 3 => 'FINAL CALL'];
        $stages = [];

        for ($stage = self::FINAL_CALL_COUNT; $stage >= 1; $stage--) {
            // Stage 3 (final) sits closest to zero.
            $at = $interval * (self::FINAL_CALL_COUNT - $stage + 1);
            $stages[] = [
                'at' => $at,
                'stage' => $stage,
                'label' => $labels[$stage] ?? ('CALL ' . $stage),
                'is_final' => $stage === self::FINAL_CALL_COUNT,
            ];
        }

        return $stages;
    }

    /**
     * The call that applies at a given seconds-remaining, or null when the clock is
     * still outside the closing window.
     *
     * @return array{at: int, stage: int, label: string, is_final: bool}|null
     */
    public function finalCallFor(?int $remaining): ?array
    {
        if ($remaining === null || ! $this->finalCallApplies()) {
            return null;
        }

        // Thresholds run final-first, so the first match is the most advanced call
        // the clock has reached.
        foreach ($this->finalCallStages() as $stage) {
            if ($remaining <= $stage['at']) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Configured quick-bid jump amounts, cleaned and sorted.
     *
     * @return list<float>
     */
    public function quickBidSteps(): array
    {
        $steps = $this->quick_bid_steps;

        if (! is_array($steps)) {
            return [];
        }

        $clean = [];
        foreach ($steps as $step) {
            $value = is_array($step) ? ($step['amount'] ?? null) : $step;
            if (is_numeric($value) && (float) $value > 0) {
                $clean[] = (float) $value;
            }
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }

    /**
     * Squad size the reserve rule holds purse back for.
     */
    public function minSquadSize(): int
    {
        return (int) ($this->min_squad_size ?: self::DEFAULT_MIN_SQUAD_SIZE);
    }

    /**
     * Price the reserve rule assumes each unfilled squad slot will cost.
     * Falls back to the auction base price so the rule still bites on auctions
     * created before this field existed.
     */
    public function minPricePerPlayer(): float
    {
        $configured = (float) ($this->min_price_per_player ?? 0);

        return $configured > 0 ? $configured : (float) ($this->base_price ?? 0);
    }

    /**
     * Check if this auction has online/offline mode configured.
     */
    public function hasOnlineOfflineMode(): bool
    {
        return $this->online_bid_limit_from !== null
            && $this->online_bid_limit_to !== null;
    }

    /**
     * Check if this auction has auto phase transition (open → closed → offline) configured.
     */
    public function hasAutoPhaseTransition(): bool
    {
        return $this->closed_bid_starts_at !== null;
    }

    public function isOnlineMode(): bool
    {
        return $this->open_bid_mode === 'online';
    }

    public function isOfflineMode(): bool
    {
        return $this->open_bid_mode === 'offline';
    }

    /**
     * Determine the expected bid phase and mode based on the current price.
     */
    public function getExpectedBidPhase(float $price): array
    {
        $bidType = 'open';
        $mode = 'online';

        if ($this->closed_bid_starts_at !== null && $price >= (float) $this->closed_bid_starts_at) {
            $bidType = 'closed';
        }
        if ($this->hasOnlineOfflineMode() && $price > (float) $this->online_bid_limit_to) {
            $mode = 'offline';
        }

        return ['bid_type' => $bidType, 'open_bid_mode' => $mode];
    }

    /**
     * Determine the expected bid mode based on the current price.
     */
    public function getExpectedBidMode(float $price): string
    {
        if (! $this->hasOnlineOfflineMode()) {
            return 'online';
        }

        if ($price > (float) $this->online_bid_limit_to) {
            return 'offline';
        }

        return 'online';
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image ? asset('storage/' . $this->background_image) : null;
    }

    public function getAuctionLogoUrlAttribute(): ?string
    {
        return $this->auction_logo ? asset('storage/' . $this->auction_logo) : null;
    }

    public function getWaitingBackgroundImageUrlAttribute(): ?string
    {
        return $this->waiting_background_image ? asset('storage/' . $this->waiting_background_image) : null;
    }

    public function players()
    {
        return $this->hasMany(AuctionPlayer::class);
    }
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    public function auctionPlayers()
    {
        return $this->hasMany(AuctionPlayer::class);
    }
    public function pools()
    {
        return $this->hasMany(AuctionPool::class)->orderBy('sequence');
    }
    public function teamBudgets()
    {
        return $this->hasMany(AuctionTeamBudget::class);
    }
    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }

    /**
     * Get all players in this auction through AuctionPlayer
     */
    public function allPlayers()
    {
        return $this->hasManyThrough(
            Player::class,
            AuctionPlayer::class,
            'auction_id',
            'id',
            'id',
            'player_id'
        );
    }
}
