<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class Auction extends Model
{
    use BelongsToOrganization;

    /** The sold board: every player sold, as a grid of cards. */
    public const BOARD_SOLD = 'sold';

    /** The highlights reel: the biggest buys, on rotating slides, for a pause. */
    public const BOARD_HIGHLIGHTS = 'highlights';

    /** @return list<string> */
    public static function publicBoards(): array
    {
        return [self::BOARD_SOLD, self::BOARD_HIGHLIGHTS];
    }

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
        'max_bid_pct_of_budget',
        'amount_unit',
        'amount_unit_label',
        'show_squad_values',
        'show_acquisition_badge',
        'overrides_tournament_rules',
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
        'timer_paused_at',
        'final_call_enabled',
        'final_call_interval_seconds',
        'open_bid_mode',
        // Which board the public screens are showing instead of the live card, if any.
        'public_board',
        'public_board_target',
        'break_ends_at',
        'online_bid_limit_from',
        'online_bid_limit_to',
        'mode_manually_overridden',
        'closed_bid_starts_at',
        'closed_bid_min_rule',
        'closed_bid_min_offset',
        'background_image',
        'auction_logo',
        'waiting_background_image',
        'primary_color',
        'secondary_color',
        'max_squad_size',
        'auction_template_id',
        'ticker_template_id',
        'default_retained_value',
        'expected_retained_per_team',
        'closed_bid_step',
        'closed_bid_max_pct_of_budget',
        'closed_bid_max_rebid_rounds',
        'closed_bid_timer_seconds',
        'closed_bid_requires_acceptance',
        'closed_bid_auto_rebid',
        'closed_bid_tie_breaker',
        'restarted_at',
        'bid_type_manually_overridden',
    ];
    protected $casts = [
        'break_ends_at' => 'datetime',

        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'bid_rules' => 'array',
        'bid_timer_seconds' => 'integer',
        'bid_timer_reset_seconds' => 'integer',
        'online_bid_limit_from' => 'decimal:2',
        'online_bid_limit_to' => 'decimal:2',
        'mode_manually_overridden' => 'boolean',
        'bid_type_manually_overridden' => 'boolean',
        'closed_bid_starts_at' => 'decimal:2',
        'min_squad_size' => 'integer',
        'max_squad_size' => 'integer',
        'min_price_per_player' => 'decimal:2',
        'max_bid_pct_of_budget' => 'decimal:2',
        'default_retained_value' => 'decimal:2',
        'expected_retained_per_team' => 'integer',
        'closed_bid_step' => 'decimal:2',
        'closed_bid_max_pct_of_budget' => 'decimal:2',
        'closed_bid_max_rebid_rounds' => 'integer',
        'closed_bid_timer_seconds' => 'integer',
        'closed_bid_requires_acceptance' => 'boolean',
        'closed_bid_auto_rebid' => 'boolean',
        'restarted_at' => 'datetime',
        'quick_bid_steps' => 'array',
        'timer_enabled' => 'boolean',
        'timer_started_at' => 'datetime',
        'timer_paused_at' => 'datetime',
        'final_call_enabled' => 'boolean',
        'final_call_interval_seconds' => 'integer',
        'notifications_enabled' => 'boolean',
        'show_squad_values' => 'boolean',
        'show_acquisition_badge' => 'boolean',
        'overrides_tournament_rules' => 'boolean',
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

    /**
     * Whether squad lists show what each player cost.
     *
     * Prices are useful to the organizer and awkward on a team manager's screen, which is often
     * on a shared table — what a rival paid is exactly the number people lean over to read. The
     * The Icon Player badge is unaffected; only the money is withheld. Defaults to true,
     * which is what every squad view did before the setting existed.
     */
    /**
     * Whether squad lists show HOW a player was acquired.
     *
     * The Icon Player / Auction badge, separately from the money. A competition may be happy for
     * everyone to see who bought whom and still not want a rival's kept players advertised —
     * these are two different disclosures and were one switch.
     *
     * Same resolution as showsSquadValues(): the tournament decides unless this auction
     * overrides. NOT NULL with a default, so it always has an opinion and is read directly.
     */
    public function showsAcquisitionBadge(): bool
    {
        if (! $this->overrides_tournament_rules && $this->tournament?->settings) {
            return (bool) $this->tournament->settings->show_acquisition_badge;
        }

        // Overriding, so this auction answers for itself — the same shape as
        // showsSquadValues(). Without its own column an overriding auction had no way to say
        // anything and silently fell back to always-on.
        return (bool) ($this->show_acquisition_badge ?? true);
    }

    public function showsSquadValues(): bool
    {
        /*
         * The tournament decides unless this auction overrides.
         *
         * `show_amounts` is NOT NULL with a default of true, so it always has an opinion — which
         * is why this reads it directly rather than through rule(), whose null-means-undecided
         * step would never fire.
         */
        if (! $this->overrides_tournament_rules && $this->tournament?->settings) {
            return (bool) $this->tournament->settings->show_amounts;
        }

        return (bool) ($this->show_squad_values ?? true);
    }

    /** Default squad size used when the auction has no explicit rule. */
    public const DEFAULT_MIN_SQUAD_SIZE = 11;

    /** What a retained player costs their team when nothing more specific is known. */
    public const DEFAULT_RETAINED_VALUE = 5000000;

    /** How many players a team is expected to retain. Advisory only — never enforced. */
    public const DEFAULT_EXPECTED_RETAINED_PER_TEAM = 4;

    /** Sealed amounts must land on this grid. 0.1M — no 0.05M, no arbitrary figure. */
    public const DEFAULT_CLOSED_BID_STEP = 100000;

    /** Most of its TOTAL budget a team may commit to a single player. */
    public const DEFAULT_CLOSED_BID_MAX_PCT = 70.0;

    /** Tie-break rounds before the lot: rounds 1, 2, 3 then draw. */
    public const CLOSED_BID_MAX_REBIDS = 2;

    public const TIE_BREAKER_LOT = 'lot';
    public const TIE_BREAKER_MANUAL = 'manual';

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

        /*
         * Measured to the moment of the pause, not to now.
         *
         * The countdown is wall-clock arithmetic, so before this it kept running through a
         * pause: pausing a 30-second timer for a minute brought the player back already
         * expired, and with timer_expiry_action = auto_sell they were sold the instant the
         * room resumed. Resuming shifts `timer_started_at` forward by the paused duration,
         * so the same number of seconds is left as when it stopped.
         */
        $until = $this->timerIsPaused()
            ? $this->timer_paused_at->getTimestamp()
            : now()->getTimestamp();

        // Integer timestamps, not diffInSeconds(): that returns a float in Carbon 3, so
        // a fraction of a second elapsed (the column stores whole seconds, the clock
        // does not) silently ate a full second off every countdown.
        $elapsed = max(0, $until - $this->timer_started_at->getTimestamp());

        return max(0, $this->timerLimitSeconds($afterBid) - $elapsed);
    }

    /**
     * Start the clock for the player now on the block.
     *
     * Server-stamped, so a slow or tampered browser cannot extend the round.
     */
    public function startTimer(): void
    {
        $this->update(['timer_started_at' => now(), 'timer_paused_at' => null]);
    }

    /**
     * Stop the clock, because nobody is on the block.
     *
     * The clock used to be left running when a player was sold or passed — it was only ever
     * cleared by a full restart. So it counted on through the gap between players and was
     * already expired by the time the next one came up: `timerHasExpired()` returned true
     * with nobody up at all, which with timer_expiry_action = auto_sell is the last thing
     * that should be true while an organizer is choosing who to auction next.
     */
    public function stopTimer(): void
    {
        if ($this->timer_started_at !== null || $this->timer_paused_at !== null) {
            $this->update(['timer_started_at' => null, 'timer_paused_at' => null]);
        }
    }

    /** Is the bid clock frozen? Distinct from the auction being paused: only a running
     *  clock can be frozen, and a paused auction with no player on the block has none. */
    public function timerIsPaused(): bool
    {
        return $this->timer_paused_at !== null;
    }

    /**
     * Freeze the clock, so a pause does not eat the player's remaining seconds.
     * Idempotent — pausing twice must not move the mark.
     */
    public function pauseTimer(): void
    {
        if ($this->timer_started_at !== null && ! $this->timerIsPaused()) {
            $this->update(['timer_paused_at' => now()]);
        }
    }

    /**
     * Resume, giving back exactly the time that was left.
     *
     * The start mark moves forward by the length of the pause rather than the remaining
     * seconds being written back, so there is one source of truth (`timer_started_at`) and
     * no rounding creeps in across repeated pauses.
     */
    public function resumeTimer(): void
    {
        if (! $this->timerIsPaused()) {
            return;
        }

        $pausedFor = max(0, now()->getTimestamp() - $this->timer_paused_at->getTimestamp());

        $this->update([
            'timer_started_at' => $this->timer_started_at?->copy()->addSeconds($pausedFor),
            'timer_paused_at' => null,
        ]);
    }

    public function timerHasExpired(bool $afterBid = false): bool
    {
        // A frozen clock cannot expire, or pausing at 0:01 would auto-sell on resume.
        if ($this->timerIsPaused()) {
            return false;
        }

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
        /*
         * A timer belongs to the player on the block. With nobody up there is nothing to
         * count down, and reporting one meant every screen showed a stale countdown — often
         * already at zero, with a FINAL CALL on it — for a player who had been sold minutes
         * earlier. One guard here covers the panel, the wall and the ticker, rather than
         * three clients each having to remember to check.
         */
        if ($auctionPlayer === null || $auctionPlayer->status !== 'on_auction') {
            return [
                'applies' => false,
                'limit' => $this->timerLimitSeconds(false),
                'remaining' => null,
                'expired' => false,
                'after_bid' => false,
                'paused' => false,
                'final_call' => null,
                'final_call_stages' => $this->finalCallStages(),
            ];
        }

        $afterBid = $auctionPlayer->current_bid_team_id !== null;
        $remaining = $this->timerSecondsRemaining($afterBid);

        return [
            'applies' => $this->timerApplies(),
            'limit' => $this->timerLimitSeconds($afterBid),
            'remaining' => $remaining,
            'expired' => $this->timerHasExpired($afterBid),
            'after_bid' => $afterBid,
            // Every screen reads this, so the hall, the stream and the operator agree that
            // the clock is stopped rather than each guessing from `status`.
            'paused' => $this->timerIsPaused(),
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
        return (int) ($this->rule('min_players_per_team', $this->min_squad_size)
            ?: self::DEFAULT_MIN_SQUAD_SIZE);
    }

    /**
     * A squad rule, from the tournament unless this auction overrides it.
     *
     * Squad size, how many icon players a team keeps and what they cost, the price a player
     * starts at — these are facts about the competition, not about one auction evening. They
     * lived only on `auctions`, so a tournament running two auctions had to have them typed
     * twice and could disagree with itself, and `min_players_per_team` / `max_players_per_team`
     * were already collected on the tournament's edit screen and read by nothing at all.
     *
     * Resolution order, and each step is deliberate:
     *
     *  1. `overrides_tournament_rules` — the organizer has said this auction is different, so
     *     the tournament is not consulted. Existing auctions were all set to this by the
     *     migration: they carry numbers somebody chose, and inheriting is opt-in for them.
     *  2. The tournament's setting, when it is not null. Null means "not decided here".
     *  3. The auction's own column, which is what every auction used before this existed.
     *
     * Returns null when nothing anywhere has an answer, so each caller keeps its own final
     * default — those differ, and meaningfully: an unset max squad size renders "—" while an
     * unset retention count means "none expected".
     */
    private function rule(string $tournamentColumn, mixed $ownValue): mixed
    {
        if ($this->overrides_tournament_rules) {
            return $ownValue;
        }

        $fromTournament = $this->tournament?->settings?->{$tournamentColumn};

        return $fromTournament !== null ? $fromTournament : $ownValue;
    }

    /**
     * Price the reserve rule assumes each unfilled squad slot will cost.
     * Falls back to the auction base price so the rule still bites on auctions
     * created before this field existed.
     */
    public function minPricePerPlayer(): float
    {
        $configured = (float) ($this->min_price_per_player ?? 0);

        return $configured > 0 ? $configured : $this->playerBasePrice();
    }

    /**
     * What a player starts at, from the tournament unless this auction overrides it.
     *
     * The one place that answers "base price", so the reserve rule, a new pool's default and a
     * poster all quote the same figure.
     */
    public function playerBasePrice(): float
    {
        $resolved = $this->rule('player_base_value', $this->base_price);

        return (float) ($resolved ?? 0);
    }

    /**
     * Squad ceiling, for display only.
     *
     * Returns null rather than falling back to minSquadSize(), so an unconfigured
     * auction can render "MAX: —" instead of a number nobody chose. Deliberately not
     * consulted by the reserve rule or the bid guards — see the migration.
     */
    public function maxSquadSize(): ?int
    {
        $resolved = $this->rule('max_players_per_team', $this->max_squad_size);

        return $resolved !== null ? (int) $resolved : null;
    }

    /**
     * What a retained player costs when no price was entered for them.
     *
     * `!== null`, not `?:` — an explicit 0 means retentions are free here, and must
     * survive. That distinction is the whole reason the column is nullable.
     */
    /**
     * Seconds left in the current break, or null when none is running.
     *
     * Computed here so every screen counts down to the same instant. If each one started its own
     * clock from whenever it happened to poll, a projector and a phone would show times seconds
     * apart — the same reason the restart notice is server-computed.
     *
     * Never negative: a break that has run over shows 0, and the wall says so, rather than
     * counting up into a number that reads like a fault.
     */
    /** Sponsor artwork for the public screens — see AuctionAd. */
    public function ads()
    {
        return $this->hasMany(AuctionAd::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Which screens a board plays on: `wall`, `ticker`, or `both`. */
    public static function boardTargets(): array
    {
        return ['both', 'wall', 'ticker'];
    }

    /**
     * Should THIS screen show the board the organizer put up?
     *
     * Asked by each screen for itself, so the wall and the ticker can be given different
     * instructions without either having to know what the other was told.
     */
    public function boardShowsOn(string $screen): bool
    {
        if ($this->public_board === null) {
            return false;
        }

        $target = $this->public_board_target ?: 'both';

        return $target === 'both' || $target === $screen;
    }

    public function breakRemaining(): ?int
    {
        if (! $this->break_ends_at) {
            return null;
        }

        return max(0, now()->diffInSeconds($this->break_ends_at, false));
    }

    public function defaultRetainedValue(): float
    {
        $resolved = $this->rule('icon_player_value', $this->default_retained_value);

        return $resolved !== null ? (float) $resolved : (float) self::DEFAULT_RETAINED_VALUE;
    }

    /**
     * How many retentions each team is expected to have.
     *
     * Used to pre-fill and to flag teams that differ. 0 means "none expected", which
     * suppresses the warning entirely.
     */
    public function expectedRetainedPerTeam(): int
    {
        $resolved = $this->rule('icon_players_per_team', $this->expected_retained_per_team);

        return $resolved !== null ? (int) $resolved : self::DEFAULT_EXPECTED_RETAINED_PER_TEAM;
    }

    /*
    |--------------------------------------------------------------------------
    | Sealed (closed) bidding
    |--------------------------------------------------------------------------
    */

    /**
     * The grid sealed amounts must land on.
     *
     * `!== null` rather than `?:`, like the retention default above — except that here a
     * step of 0 is meaningless rather than expressive, so validation refuses it
     * (`min:0.01`) and this accessor never has to defend against one. Do not "fix" this
     * to `?:` on the assumption that 0 means unset.
     */
    public function closedBidStep(): float
    {
        return $this->closed_bid_step !== null
            ? (float) $this->closed_bid_step
            : (float) self::DEFAULT_CLOSED_BID_STEP;
    }

    /**
     * Ceiling on what one player may cost a team, as a percentage of its TOTAL allocated
     * budget — not its remaining purse, and not its post-retention purse. Fixed for the
     * auction, so the figure a team is shown never moves under it.
     */
    public function closedBidMaxPct(): float
    {
        return $this->closed_bid_max_pct_of_budget !== null
            ? (float) $this->closed_bid_max_pct_of_budget
            : self::DEFAULT_CLOSED_BID_MAX_PCT;
    }

    /**
     * Share of a team's allocation it may commit to any ONE player in open bidding.
     *
     * NULL means no ceiling, and that is the default: an auction that does not configure this
     * is bound only by the squad-reserve rule, exactly as before. Returned as a nullable float
     * rather than defaulted like closedBidMaxPct(), because "not configured" and "100%" are
     * different statements and only one of them should show a ceiling on a team's screen.
     */
    public function maxBidPct(): ?float
    {
        return $this->max_bid_pct_of_budget !== null
            ? (float) $this->max_bid_pct_of_budget
            : null;
    }

    /** 0 is meaningful: it sends a tie straight to the lot with no re-bid. */
    public function closedBidMaxRebidRounds(): int
    {
        return $this->closed_bid_max_rebid_rounds !== null
            ? (int) $this->closed_bid_max_rebid_rounds
            : self::CLOSED_BID_MAX_REBIDS;
    }

    /** Rounds in the ladder, including the first: 1 + re-bids. */
    /**
     * Does a tied sealed round open its own re-bid?
     *
     * Off by default, and off is the behaviour every auction had before the setting existed: the
     * round stops at `tie` and waits for the organizer. On, the next round opens the moment the
     * reveal finds a tie — which is what a room expects, since the pause has nothing in it.
     */
    public function autoRebidsOnTie(): bool
    {
        return (bool) ($this->closed_bid_auto_rebid ?? false);
    }

    /** How the minimum sealed bid is derived — see ClosedBidService::floorFor(). */
    public const CLOSED_BID_MIN_PRICE = 'price';
    public const CLOSED_BID_MIN_STEP = 'price_plus_step';
    public const CLOSED_BID_MIN_FIXED = 'price_plus_fixed';

    /**
     * Defaults to one step above the standing price.
     *
     * Not to the price itself: a floor equal to the standing bid lets a sealed bid MATCH the open
     * bid and win, since the sealed round replaces the open one.
     */
    public function closedBidMinRule(): string
    {
        return in_array($this->closed_bid_min_rule, [
            self::CLOSED_BID_MIN_PRICE,
            self::CLOSED_BID_MIN_STEP,
            self::CLOSED_BID_MIN_FIXED,
        ], true) ? $this->closed_bid_min_rule : self::CLOSED_BID_MIN_STEP;
    }

    /** Only consulted by the `price_plus_fixed` rule. */
    public function closedBidMinOffset(): float
    {
        return (float) ($this->closed_bid_min_offset ?? 0);
    }

    public function closedBidTotalRounds(): int
    {
        return 1 + $this->closedBidMaxRebidRounds();
    }

    /** Falls back to the open-bid clock when the sealed round has none of its own. */
    public function closedBidTimerSeconds(): int
    {
        return $this->closed_bid_timer_seconds !== null
            ? (int) $this->closed_bid_timer_seconds
            : (int) ($this->bid_timer_seconds ?: 30);
    }

    /** Must a team explicitly accept the purse conditions before it may bid? */
    public function closedBidRequiresAcceptance(): bool
    {
        /*
         * Never in an offline room.
         *
         * Acceptance exists so a team can read its purse, its remaining places and its ceiling
         * and say yes before committing. In an offline auction the organizer is calling the
         * round in front of everyone and the teams are in the room — there is nothing to accept
         * that has not just been said out loud, and the screen ends up offering ACCEPT and
         * WITHDRAW where the only useful control is the amount box.
         *
         * Decided here rather than in the view so the API and the UI cannot disagree: the state
         * payload, the submit guard and the team's screen all read this one method.
         */
        if ($this->open_bid_mode === 'offline') {
            return false;
        }

        /*
         * Nor anywhere else, now — acceptance has been removed on request.
         *
         * The step never paid for itself. A team that wants the player enters an amount, and a
         * team that does not simply enters nothing; accepting first said no more than that, and
         * it added a way to be excluded from a round by forgetting to press a button. Online
         * rooms hit that hardest, because the round can open while a manager is reading their
         * purse rather than watching for a prompt.
         *
         * Kept as one early return rather than deleted, so the reasoning survives and turning it
         * back on is one line. `closed_bid_requires_acceptance` stays on the table for the same
         * reason: the column is what a future setting would read, and dropping it would throw
         * away the per-auction choice as well as the current default.
         */
        return false;
    }

    /** How long the big screen announces a restart before carrying on. */
    public const RESTART_NOTICE_SECONDS = 10;

    /**
     * Is the auction inside its post-restart announcement window?
     *
     * Answered by the server so every screen watching agrees. If each client timed its
     * own ten seconds from whenever it happened to poll, a projector and an OBS source
     * would come back at different moments.
     */
    public function isRestarting(): bool
    {
        return $this->restarted_at !== null
            && now()->getTimestamp() - $this->restarted_at->getTimestamp() < self::RESTART_NOTICE_SECONDS;
    }

    /** Seconds left on that announcement, or null when it is not showing. */
    public function restartNoticeRemaining(): ?int
    {
        if (! $this->isRestarting()) {
            return null;
        }

        return max(0, self::RESTART_NOTICE_SECONDS - (now()->getTimestamp() - $this->restarted_at->getTimestamp()));
    }

    public function closedBidTieBreaker(): string
    {
        return $this->closed_bid_tie_breaker ?: self::TIE_BREAKER_LOT;
    }

    /**
     * Clock state for a sealed round.
     *
     * The round owns its own clock rather than using `timerStateFor()`, which picks the
     * short `bid_timer_reset_seconds` limit whenever `current_bid_team_id` is set — and
     * during a sealed round that is the frozen open-bid leader, so the round would
     * silently get the wrong limit.
     *
     * Integer timestamps, not diffInSeconds(): see timerSecondsRemaining().
     *
     * @return array{applies: bool, limit: int, remaining: int|null, expired: bool}
     */
    public function closedBidRoundTimerState(AuctionClosedBidRound $round): array
    {
        $limit = (int) ($round->timer_seconds ?: $this->closedBidTimerSeconds());

        if ($round->timer_started_at === null) {
            return ['applies' => true, 'limit' => $limit, 'remaining' => null, 'expired' => false];
        }

        $elapsed = max(0, now()->getTimestamp() - $round->timer_started_at->getTimestamp());
        $remaining = max(0, $limit - $elapsed);

        return [
            'applies' => true,
            'limit' => $limit,
            'remaining' => $remaining,
            'expired' => $remaining <= 0,
        ];
    }

    /**
     * Check if this auction has online/offline mode configured.
     */
    public function hasOnlineOfflineMode(): bool
    {
        /*
         * The UPPER bound alone decides this — "Organizer Enters Bids From".
         *
         * It demanded both bounds, so auction 11, which has only the upper one set, had a
         * configured figure on the form and a handover that never happened. Requiring both was
         * the bug.
         *
         * But the lower bound must NOT switch it on, which is what I changed it to first. That
         * field is labelled "Online Bid Starts From" and its own help text says "Informational
         * only — it changes nothing on its own": it records where an organizer intends bidding
         * to open and has never governed anything. Making it govern would have flipped auction
         * 12 into a mode rule it had never been under, mid-event, on a deploy — and a price
         * below it would have gone OFFLINE, which is the opposite of what the label promises.
         *
         * Where bidding opens is `base_price`, and the opening bid takes it in both modes.
         */
        return $this->online_bid_limit_to !== null;
    }

    /**
     * Is this price above the point where the organizer takes over the bidding?
     *
     * One method because two callers asked it and each did so slightly differently.
     */
    private function priceIsOffline(float $price): bool
    {
        // Null-checked rather than cast: `(float) null` is 0, so an unset ceiling would read as
        // "every price is over it" and put the auction offline from its first bid.
        return $this->online_bid_limit_to !== null && $price > (float) $this->online_bid_limit_to;
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
    /**
     * Apply the automatic phase rule for a price that has just been reached.
     *
     * This rule used to be copy-pasted inline in two controllers while
     * `getExpectedBidPhase()` — the canonical version — had no callers at all. Both
     * copies are now this method, which is also the single place a sealed round gets
     * created.
     *
     * Deliberately one-way: a falling price never re-opens a closed phase, and a manual
     * override switches the automatic rule off entirely (both halves of it), which is
     * what the previous inline copies did.
     *
     * THE SEALED THRESHOLD IS NOT APPLIED WITHOUT `$sealedConfirmed`. Crossing it used to
     * tip the whole room into a sealed round the instant a bid landed on 8M, with no way
     * back: the organizer had lost the option of simply selling to the leading team, and a
     * threshold set a little too low turned every ordinary sale into a sealed round. The
     * crossing is now reported as pending and the organizer answers it — sealed round, or
     * sell to the standing top bid. The mode axis (online -> offline) still applies on its
     * own, because it changes who may bid, not what happens to the player.
     *
     * @return array{bid_type_changed: bool, bid_type_pending: bool, open_bid_mode_changed: bool}
     */
    public function applyAutoPhase(float $price, bool $sealedConfirmed = false): array
    {
        $expected = $this->getExpectedBidPhase($price);
        $changes = [];
        $sealedPending = false;

        // The two axes are judged separately. Sharing one override flag meant that
        // choosing to run the room offline also silenced the sealed-bid threshold — so an
        // offline auction sailed straight past it.
        if (! $this->bid_type_manually_overridden
            && $this->hasAutoPhaseTransition()
            && $this->bid_type === 'open'
            && $expected['bid_type'] === 'closed') {
            if ($sealedConfirmed) {
                $changes['bid_type'] = 'closed';
            } else {
                $sealedPending = true;
            }
        }

        if (! $this->mode_manually_overridden
            && $this->hasOnlineOfflineMode()
            && $this->open_bid_mode === 'online'
            && $expected['open_bid_mode'] === 'offline') {
            $changes['open_bid_mode'] = 'offline';
        }

        if ($changes !== []) {
            $this->update($changes);
        }

        return [
            'bid_type_changed' => isset($changes['bid_type']),
            'bid_type_pending' => $sealedPending,
            'open_bid_mode_changed' => isset($changes['open_bid_mode']),
        ];
    }

    /**
     * Has this player's price crossed the sealed threshold with nobody having decided
     * what to do about it?
     *
     * Derived rather than stored, so there is no flag to leave behind: the question stops
     * being asked the moment the auction turns `closed` (the organizer said yes) or the
     * player leaves the block (the organizer sold them instead, or passed).
     */
    public function sealedThresholdPendingFor(?AuctionPlayer $auctionPlayer): bool
    {
        if ($auctionPlayer === null || $auctionPlayer->status !== 'on_auction') {
            return false;
        }

        return $this->hasAutoPhaseTransition()
            && ! $this->bid_type_manually_overridden
            && $this->bid_type === 'open'
            && $auctionPlayer->closed_bid_round_id === null
            && (float) $auctionPlayer->current_price >= (float) $this->closed_bid_starts_at;
    }

    public function getExpectedBidPhase(float $price): array
    {
        $bidType = 'open';
        $mode = 'online';

        if ($this->closed_bid_starts_at !== null && $price >= (float) $this->closed_bid_starts_at) {
            $bidType = 'closed';
        }
        if ($this->hasOnlineOfflineMode() && $this->priceIsOffline($price)) {
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

        return $this->priceIsOffline($price) ? 'offline' : 'online';
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
