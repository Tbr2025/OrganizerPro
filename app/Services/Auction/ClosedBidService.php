<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\ActualTeam;
use App\Models\AuctionActionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The sealed-bid round: creating it, reading its state, and (later) running it.
 *
 * A sealed round is a distinct episode in a player's auction. Open bidding runs until
 * the price reaches the configured threshold, at which point the amounts stop being
 * public: teams type a figure nobody else can see, the highest wins, and a tie goes to a
 * re-bid ladder and then to a drawn lot.
 *
 * Everything here works on the round row rather than `auction_players.status`. The player
 * stays `on_auction` for the whole episode.
 */
class ClosedBidService
{
    public function __construct(
        private readonly BidIncrementService $increments,
        private readonly AuctionPoolService $pools,
        private readonly AuctionSaleService $sales,
        private readonly AuctionUndoService $undo,
        private readonly ClosedBidLotService $lots,
    ) {
    }

    /**
     * The round currently in play for a player, if any.
     *
     * Reads the pointer rather than searching, so there is one answer to "which round is
     * this player in" and it cannot disagree with itself.
     */
    public function currentRound(AuctionPlayer $auctionPlayer): ?AuctionClosedBidRound
    {
        if (! $auctionPlayer->closed_bid_round_id) {
            return null;
        }

        return $auctionPlayer->closedBidRound()->first();
    }

    /** Is a sealed round open for this player right now? */
    public function hasOpenRound(AuctionPlayer $auctionPlayer): bool
    {
        $round = $this->currentRound($auctionPlayer);

        return $round !== null && ! $round->isTerminal();
    }

    /**
     * The lowest legal sealed amount for a round opening at this price.
     *
     * Snapped UP onto the step grid, because an off-grid floor is worse than useless: a
     * browser validates a number input as `(value - min) % step === 0`, so a floor of
     * 8.05M with a 0.1M step would have the browser accept 9.05M and reject 9.0M —
     * exactly inverted. The server publishes a snapped floor so the two agree.
     */
    public function floorFor(Auction $auction, float $price): float
    {
        $threshold = $auction->closed_bid_starts_at !== null
            ? (float) $auction->closed_bid_starts_at
            : 0.0;

        return $this->increments->snapUpToStep($auction, max($threshold, $price));
    }

    /**
     * Open a sealed round for a player whose price has reached the threshold.
     *
     * Idempotent by construction: the unique index on
     * (auction_player_id, attempt_no, round_number) means a concurrent second call
     * collides rather than creating a duplicate round, so two organizer panels or a
     * racing bid cannot produce two rounds for one player.
     *
     * Returns the existing round when one is already open, so callers can call this
     * unconditionally after any accepted bid.
     */
    public function openRoundFor(AuctionPlayer $auctionPlayer, ?Auction $auction = null): ?AuctionClosedBidRound
    {
        $auction ??= $auctionPlayer->auction;

        if (! $auction || $auction->bid_type !== 'closed') {
            return null;
        }

        if ($existing = $this->currentRound($auctionPlayer)) {
            return $existing->isTerminal() ? null : $existing;
        }

        return DB::transaction(function () use ($auctionPlayer, $auction) {
            // A re-auctioned player gets a fresh episode rather than reusing the old
            // one — the previous round's record is kept for the audit.
            $attempt = (int) AuctionClosedBidRound::where('auction_player_id', $auctionPlayer->id)
                ->max('attempt_no');

            $price = (float) $auctionPlayer->current_price;
            $floor = $this->floorFor($auction, $price);

            $round = AuctionClosedBidRound::firstOrCreate(
                [
                    'auction_player_id' => $auctionPlayer->id,
                    'attempt_no' => max(1, $attempt + 1),
                    'round_number' => 1,
                ],
                [
                    'auction_id' => $auction->id,
                    'state' => AuctionClosedBidRound::STATE_PENDING,
                    'floor' => $floor,
                    // Snapshot the rules in force, so this round stays defensible even if
                    // the auction is reconfigured afterwards.
                    'step' => $auction->closedBidStep(),
                    'max_pct_of_budget' => $auction->closedBidMaxPct(),
                    // Who was leading when open bidding stopped. This is who takes the
                    // player if nobody enters the sealed round.
                    'leader_team_id' => $auctionPlayer->current_bid_team_id,
                    'leader_amount' => $price,
                    'timer_seconds' => $auction->closedBidTimerSeconds(),
                ]
            );

            $auctionPlayer->update(['closed_bid_round_id' => $round->id]);

            return $round;
        });
    }

    /**
     * Abandon every open round for a player.
     *
     * Called when a player is re-bid or re-auctioned. Rounds and entries are never
     * deleted — the record of who bid what, and how a contested player was resolved, is
     * the whole point of the tables. `rebidPlayer()` deletes bids and action logs, so
     * this is the only durable trail of a disputed round.
     */
    public function abandonRoundsFor(AuctionPlayer $auctionPlayer): int
    {
        $abandoned = AuctionClosedBidRound::where('auction_player_id', $auctionPlayer->id)
            ->open()
            ->update([
                'state' => AuctionClosedBidRound::STATE_ABANDONED,
                'resolution' => AuctionClosedBidRound::RESOLUTION_ABANDONED,
                'abandoned_at' => now(),
            ]);

        if ($auctionPlayer->closed_bid_round_id) {
            $auctionPlayer->update(['closed_bid_round_id' => null]);
        }

        return $abandoned;
    }

    /**
     * What a TEAM may know about the round.
     *
     * Never any rival's amount, and never a count that would identify one. During a
     * tie-break the team is told the tied amount and how many teams shared it — that
     * names nobody — but never which teams, and never a losing amount.
     */
    public function stateForTeam(Auction $auction, ?AuctionPlayer $auctionPlayer, ?int $actualTeamId): array
    {
        $round = $auctionPlayer ? $this->currentRound($auctionPlayer) : null;

        if (! $round) {
            return ['active' => false];
        }

        $entry = $actualTeamId
            ? $round->entries()->where('actual_team_id', $actualTeamId)->first()
            : null;

        $purse = $actualTeamId ? $this->pools->teamPurseState($auction, $actualTeamId) : null;
        $timer = $auction->closedBidRoundTimerState($round);

        return [
            'active' => true,
            'round_id' => $round->id,
            'state' => $round->state,
            'round_number' => $round->round_number,
            'total_rounds' => $auction->closedBidTotalRounds(),
            'floor' => (float) $round->floor,
            'step' => (float) $round->step,
            'requires_acceptance' => $auction->closedBidRequiresAcceptance(),
            'timer' => $timer,

            // The tied amount is public to the round's participants; who tied is not.
            'tie_amount' => $round->tie_amount !== null ? (float) $round->tie_amount : null,
            'tied_count' => is_array($round->tied_team_ids) ? count($round->tied_team_ids) : null,

            'my_entry' => $entry ? [
                'state' => $entry->state,
                'amount' => $entry->amount !== null ? (float) $entry->amount : null,
                'required' => (bool) $entry->required,
                'withdrawn' => $entry->isWithdrawn(),
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
            ] : null,

            // Both ceilings, so a team can see which rule is holding it back rather than
            // guessing whether it is broke or simply capped.
            'ceilings' => $purse ? [
                'per_player_cap' => $this->cap($purse['per_player_cap']),
                'per_player_cap_pct' => $purse['per_player_cap_pct'],
                'reserve_max' => $this->cap($purse['max_bid_allowed']),
                'binding' => $this->cap($purse['sealed_max_bid']),
                'reserve_amount' => $purse['reserve'],
                'slots_remaining' => $purse['slots_remaining'],
                'remaining_budget' => $this->cap($purse['remaining']),
                'allocated' => $this->cap($purse['allocated']),
            ] : null,
        ];
    }

    /**
     * What the ORGANIZER may know about the round.
     *
     * Amounts are withheld until the round is revealed. The panel is routinely on a
     * projector or a shared screen, so "the organizer can see it" is not the same as
     * "only the organizer can see it".
     */
    public function stateForOrganizer(Auction $auction, ?AuctionPlayer $auctionPlayer): array
    {
        $round = $auctionPlayer ? $this->currentRound($auctionPlayer) : null;

        if (! $round) {
            return ['active' => false];
        }

        $revealed = $round->isRevealed();
        $entries = $round->entries()->with('team')->get();
        $top = $revealed
            ? (float) $entries->filter(fn ($e) => $e->isStanding())->max('amount')
            : null;

        $rows = $entries->map(function (AuctionClosedBidEntry $entry) use ($revealed, $auction, $top) {
            $purse = $this->pools->teamPurseState($auction, $entry->actual_team_id);

            $row = [
                'entry_id' => $entry->id,
                'team_id' => $entry->actual_team_id,
                'team_name' => $entry->team->name ?? 'Unknown',
                'team_logo' => $entry->team?->team_logo_url,
                'state' => $entry->state,
                'required' => (bool) $entry->required,
                'submitted' => $entry->submitted_at !== null,
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
                'withdrawn' => $entry->isWithdrawn(),
                'withdrawn_by_role' => $entry->withdrawn_by_role,
                'adjusted_count' => (int) $entry->adjusted_count,
                'per_player_cap' => $this->cap($purse['per_player_cap']),
                'reserve_max' => $this->cap($purse['max_bid_allowed']),
                'binding_ceiling' => $this->cap($purse['sealed_max_bid']),
            ];

            // Only once revealed does an amount appear — including to the organizer.
            if ($revealed) {
                $row['amount'] = $entry->amount !== null ? (float) $entry->amount : null;
                $row['is_tied'] = $entry->isStanding() && $top !== null && (float) $entry->amount === $top;
            }

            return $row;
        });

        if ($revealed) {
            $rows = $rows->sortByDesc(fn ($r) => $r['amount'] ?? -1)->values();
        }

        return [
            'active' => true,
            'round_id' => $round->id,
            'state' => $round->state,
            'round_number' => $round->round_number,
            'total_rounds' => $auction->closedBidTotalRounds(),
            'floor' => (float) $round->floor,
            'step' => (float) $round->step,
            'revealed' => $revealed,
            'timer' => $auction->closedBidRoundTimerState($round),
            'tie_amount' => $round->tie_amount !== null ? (float) $round->tie_amount : null,
            'tied_team_ids' => $round->tied_team_ids ?? [],
            'resolution' => $round->resolution,
            'winner_team_id' => $round->winner_team_id,
            'winning_amount' => $round->winning_amount !== null ? (float) $round->winning_amount : null,
            'leader' => $round->leader_team_id ? [
                'team_id' => $round->leader_team_id,
                'team_name' => $round->leaderTeam->name ?? null,
                'amount' => (float) $round->leader_amount,
            ] : null,
            'entries' => $rows,
            'counts' => [
                'invited' => $entries->count(),
                'accepted' => $entries->where('state', AuctionClosedBidEntry::STATE_ACCEPTED)->count(),
                'submitted' => $entries->filter(fn ($e) => $e->submitted_at !== null && ! $e->isWithdrawn())->count(),
                'withdrawn' => $entries->filter(fn ($e) => $e->isWithdrawn())->count(),
            ],
        ];
    }

    /**
     * What the PUBLIC may know: that a sealed round is running, and nothing else.
     *
     * Counts only — never an amount, and never a team-to-amount mapping.
     */
    public function stateForPublic(Auction $auction, ?AuctionPlayer $auctionPlayer): ?array
    {
        $round = $auctionPlayer ? $this->currentRound($auctionPlayer) : null;

        if (! $round || $round->isTerminal()) {
            return null;
        }

        /*
         * The round owns its own clock.
         *
         * The auction-level timer belongs to open bidding and is not running during a sealed
         * round, so both public screens simply showed nothing — the hall and the stream had
         * no idea how long the teams had left to submit. `closedBidRoundTimerState()` is the
         * same arithmetic the organizer's panel reads, so all three now agree.
         *
         * Counts only, as before: never an amount, never a team-to-amount mapping.
         */
        $timer = $auction->closedBidRoundTimerState($round);

        return [
            'state' => $round->state,
            'round_number' => $round->round_number,
            'total_rounds' => $auction->closedBidTotalRounds(),
            'floor' => (float) $round->floor,
            'submitted_count' => $round->entries()->standing()->count(),
            'invited_count' => $round->entries()->count(),
            'timer' => [
                'applies' => (bool) ($timer['applies'] ?? false),
                'remaining' => $timer['remaining'] ?? null,
                'limit' => $timer['limit'] ?? null,
                'expired' => (bool) ($timer['expired'] ?? false),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Running the round
    |--------------------------------------------------------------------------
    | Every mutating method re-reads its row inside a transaction under
    | lockForUpdate() and re-checks its precondition, so two open organizer panels
    | cannot both act on the same round. Lock order is always
    | auction_players -> rounds -> entries; taking them in any other order is how a
    | deadlock between "team submits" and "admin locks" would happen.
    |
    | A no-op is reported as ['handled' => false] with a 200, not an error. Two panels
    | both pressing Lock is normal operation, not a mistake to shout about.
    */

    /** Teams that may take part: every team in the auction's tournament. */
    public function eligibleTeams(Auction $auction)
    {
        // One definition, shared with the ticker and the panel: the strip must not list a
        // team the sealed round never invites.
        return $this->pools->participatingTeams($auction);
    }

    /**
     * Invite the teams and let them accept.
     *
     * Each invitation records the ceilings the team is being shown, so a later dispute
     * about "the system told me I could bid 7M" can be settled from the record.
     */
    public function openEntry(AuctionClosedBidRound $round, ?User $actor = null): array
    {
        return DB::transaction(function () use ($round, $actor) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->state !== AuctionClosedBidRound::STATE_PENDING) {
                return ['handled' => false, 'message' => 'That round is already open.', 'round' => $round];
            }

            $auction = $round->auction;

            foreach ($this->eligibleTeams($auction) as $team) {
                $purse = $this->pools->teamPurseState($auction, $team->id);

                AuctionClosedBidEntry::updateOrCreate(
                    [
                        'auction_closed_bid_round_id' => $round->id,
                        'actual_team_id' => $team->id,
                    ],
                    [
                        'auction_id' => $auction->id,
                        'state' => AuctionClosedBidEntry::STATE_INVITED,
                        'ceiling_at_entry' => $this->cap($purse['sealed_max_bid']),
                        'per_player_cap_at_entry' => $this->cap($purse['per_player_cap']),
                        'reserve_at_entry' => $purse['reserve'],
                        'slots_remaining_at_entry' => $purse['slots_remaining'],
                    ]
                );
            }

            $round->update([
                'state' => AuctionClosedBidRound::STATE_ENTRY_OPEN,
                'opened_at' => now(),
                'opened_by' => $actor?->id,
            ]);

            return ['handled' => true, 'message' => 'Teams may now enter the sealed round.', 'round' => $round->fresh()];
        });
    }

    /** Start collecting amounts, and start the round's own clock. */
    public function start(AuctionClosedBidRound $round, ?User $actor = null): array
    {
        return DB::transaction(function () use ($round, $actor) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->state === AuctionClosedBidRound::STATE_COLLECTING) {
                return ['handled' => false, 'message' => 'That round is already running.', 'round' => $round];
            }

            if (! in_array($round->state, [
                AuctionClosedBidRound::STATE_PENDING,
                AuctionClosedBidRound::STATE_ENTRY_OPEN,
            ], true)) {
                return ['handled' => false, 'message' => 'That round can no longer be started.', 'round' => $round];
            }

            // Starting without opening entry first is allowed — it just skips the gate.
            if ($round->entries()->count() === 0) {
                $this->openEntry($round, $actor);
                $round = $round->fresh();
            }

            $round->update([
                'state' => AuctionClosedBidRound::STATE_COLLECTING,
                'opened_at' => $round->opened_at ?? now(),
                'timer_started_at' => now(),
                'timer_seconds' => $round->timer_seconds ?: $round->auction->closedBidTimerSeconds(),
            ]);

            return ['handled' => true, 'message' => 'Sealed bidding is open.', 'round' => $round->fresh()];
        });
    }

    /** A team accepts the conditions and may then bid. */
    public function accept(AuctionClosedBidRound $round, ActualTeam $team, ?User $actor = null): array
    {
        return DB::transaction(function () use ($round, $team, $actor) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->isTerminal() || $round->locked_at !== null) {
                return ['handled' => false, 'message' => 'That round is closed.'];
            }

            $auction = $round->auction;
            $ceiling = $this->pools->perPlayerCeiling($auction, $team->id);

            // A team that cannot reach the opening amount cannot enter. Letting it in
            // would only produce a form it can never legally submit.
            if ($ceiling < (float) $round->floor) {
                return [
                    'handled' => false,
                    'message' => $this->pools->sealedBlockedMessage($auction, $team->id, (float) $round->floor, $team->name),
                ];
            }

            $entry = $this->entryFor($round, $team);
            $purse = $this->pools->teamPurseState($auction, $team->id);

            $entry->update([
                'state' => AuctionClosedBidEntry::STATE_ACCEPTED,
                'accepted_at' => now(),
                'declined_at' => null,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'withdrawn_by_role' => null,
                // Re-snapshot: this is the moment the team was actually shown the figures.
                'ceiling_at_entry' => $this->cap($purse['sealed_max_bid']),
                'per_player_cap_at_entry' => $this->cap($purse['per_player_cap']),
                'reserve_at_entry' => $purse['reserve'],
                'slots_remaining_at_entry' => $purse['slots_remaining'],
            ]);

            return ['handled' => true, 'message' => 'Entered the sealed round.', 'entry' => $entry->fresh()];
        });
    }

    /** A team declines the round outright. */
    public function decline(AuctionClosedBidRound $round, ActualTeam $team): array
    {
        $entry = $this->entryFor($round, $team);

        if ($round->isTerminal() || $round->locked_at !== null) {
            return ['handled' => false, 'message' => 'That round is closed.'];
        }

        $entry->update([
            'state' => AuctionClosedBidEntry::STATE_DECLINED,
            'declined_at' => now(),
            'amount' => null,
        ]);

        return ['handled' => true, 'message' => 'Left the sealed round.', 'entry' => $entry->fresh()];
    }

    /**
     * Record a team's sealed amount.
     *
     * Replaces rather than appends: the unique index means one standing amount per team
     * per round, so a second submission is an edit and cannot stack.
     *
     * @param  string  $source  'team' or 'admin' — an admin adjustment runs the same
     *                          checks, so the two can never drift apart on what is legal.
     */
    public function submit(
        AuctionClosedBidRound $round,
        ActualTeam $team,
        float $amount,
        ?User $actor = null,
        string $source = AuctionClosedBidEntry::ROLE_TEAM
    ): array {
        return DB::transaction(function () use ($round, $team, $amount, $actor, $source) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);
            $auction = $round->auction;

            if ($round->state !== AuctionClosedBidRound::STATE_COLLECTING || $round->locked_at !== null) {
                return ['handled' => false, 'message' => 'Sealed bidding is not open for this player.'];
            }

            // The clock is the server's. A submission that arrives after expiry is late,
            // however the client's own countdown looked.
            if ($auction->closedBidRoundTimerState($round)['expired']) {
                return ['handled' => false, 'message' => 'Time is up for this round.'];
            }

            if ($auction->closedBidRequiresAcceptance() && $source === AuctionClosedBidEntry::ROLE_TEAM) {
                $existing = $round->entries()->where('actual_team_id', $team->id)->first();

                if (! $existing || ! in_array($existing->state, [
                    AuctionClosedBidEntry::STATE_ACCEPTED,
                    AuctionClosedBidEntry::STATE_SUBMITTED,
                    AuctionClosedBidEntry::STATE_MUST_REBID,
                    AuctionClosedBidEntry::STATE_MAY_OPT_IN,
                ], true)) {
                    return ['handled' => false, 'message' => 'Accept the round conditions before bidding.'];
                }
            }

            // Rejected, never rounded: silently correcting an amount under time pressure
            // is indistinguishable from the system choosing a bid for you.
            if (! $this->increments->isLegalSealedAmount($auction, $amount)) {
                $near = $this->increments->nearestLegalAmounts($auction, $amount);

                return [
                    'handled' => false,
                    'message' => sprintf(
                        'Bids must be in steps of %s. %s is not allowed — try %s or %s.',
                        format_points((float) $round->step),
                        format_points($amount),
                        format_points($near['below']),
                        format_points($near['above'])
                    ),
                ];
            }

            if ($amount < (float) $round->floor) {
                return [
                    'handled' => false,
                    'message' => sprintf('Bids must be at least %s in this round.', format_points((float) $round->floor)),
                ];
            }

            // Both ceilings, recomputed now rather than read from the entry snapshot —
            // allocations can move between accepting and bidding.
            if (! $this->pools->canAffordSealed($auction, $team->id, $amount)) {
                return [
                    'handled' => false,
                    'message' => $this->pools->sealedBlockedMessage($auction, $team->id, $amount, $team->name),
                ];
            }

            $entry = $this->entryFor($round, $team);
            $before = $entry->amount !== null ? (float) $entry->amount : null;

            $entry->update([
                'state' => AuctionClosedBidEntry::STATE_SUBMITTED,
                'amount' => $amount,
                'submitted_at' => now(),
                'submitted_by' => $actor?->id,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'withdrawn_by_role' => null,
            ]);

            if ($source === AuctionClosedBidEntry::ROLE_ADMIN) {
                // Duplicated on purpose: the action log makes it undoable, this column
                // survives rebidPlayer() deleting that log.
                $entry->update([
                    'adjustments' => array_merge($entry->adjustments ?? [], [[
                        'from' => $before,
                        'to' => $amount,
                        'by' => $actor?->id,
                        'at' => now()->toIso8601String(),
                        'source' => 'admin',
                    ]]),
                    'adjusted_count' => (int) $entry->adjusted_count + 1,
                ]);
            }

            $this->undo->record(
                $auction,
                $source === AuctionClosedBidEntry::ROLE_ADMIN
                    ? AuctionActionLog::ACTION_CLOSED_ADJUST
                    : AuctionActionLog::ACTION_CLOSED_BID,
                $round->auctionPlayer,
                [
                    'entry_id' => $entry->id,
                    'round_id' => $round->id,
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'amount' => $amount,
                    'previous_amount' => $before,
                    'previous_state' => $entry->getOriginal('state'),
                ],
                sprintf('Sealed bid %s by %s', format_points($amount), $team->name)
            );

            return ['handled' => true, 'message' => 'Sealed bid recorded.', 'entry' => $entry->fresh()];
        });
    }

    /**
     * Take a team out of the round.
     *
     * No re-award path is needed anywhere: the winner is the top STANDING entry, and
     * withdrawing simply removes a row from that set, so the next-highest becomes the
     * winner by the same query.
     */
    public function withdraw(AuctionClosedBidEntry $entry, ?User $actor, string $role): array
    {
        return DB::transaction(function () use ($entry, $actor, $role) {
            $entry = AuctionClosedBidEntry::lockForUpdate()->find($entry->id);
            $round = $entry->round;

            if ($round->isTerminal()) {
                return ['handled' => false, 'message' => 'That round is finished — undo the sale instead.'];
            }

            if ($entry->isWithdrawn()) {
                return ['handled' => false, 'message' => 'That team has already withdrawn.'];
            }

            $before = $entry->state;

            $entry->update([
                'state' => AuctionClosedBidEntry::STATE_WITHDRAWN,
                'withdrawn_at' => now(),
                'withdrawn_by' => $actor?->id,
                'withdrawn_by_role' => $role,
            ]);

            $this->undo->record(
                $round->auction,
                AuctionActionLog::ACTION_CLOSED_WITHDRAW,
                $round->auctionPlayer,
                [
                    'entry_id' => $entry->id,
                    'round_id' => $round->id,
                    'team_id' => $entry->actual_team_id,
                    'action' => 'withdraw',
                    'previous_state' => $before,
                ],
                sprintf('Withdrew %s from the sealed round', $entry->team->name ?? 'a team')
            );

            return ['handled' => true, 'message' => 'Withdrawn from the round.', 'entry' => $entry->fresh()];
        });
    }

    /** Put a withdrawn team back in. Its previous amount stands again. */
    public function reinstate(AuctionClosedBidEntry $entry, ?User $actor, string $role): array
    {
        return DB::transaction(function () use ($entry, $actor, $role) {
            $entry = AuctionClosedBidEntry::lockForUpdate()->find($entry->id);
            $round = $entry->round;

            if ($round->isTerminal() || $round->locked_at !== null) {
                return ['handled' => false, 'message' => 'That round is closed.'];
            }

            if (! $entry->isWithdrawn()) {
                return ['handled' => false, 'message' => 'That team has not withdrawn.'];
            }

            $entry->update([
                'state' => $entry->amount !== null
                    ? AuctionClosedBidEntry::STATE_SUBMITTED
                    : AuctionClosedBidEntry::STATE_ACCEPTED,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'withdrawn_by_role' => null,
                'reinstated_at' => now(),
                'reinstated_by' => $actor?->id,
            ]);

            $this->undo->record(
                $round->auction,
                AuctionActionLog::ACTION_CLOSED_WITHDRAW,
                $round->auctionPlayer,
                [
                    'entry_id' => $entry->id,
                    'round_id' => $round->id,
                    'team_id' => $entry->actual_team_id,
                    'action' => 'reinstate',
                ],
                sprintf('Reinstated %s into the sealed round', $entry->team->name ?? 'a team')
            );

            return ['handled' => true, 'message' => 'Back in the round.', 'entry' => $entry->fresh()];
        });
    }

    /**
     * Close submissions and reveal the board.
     *
     * Locking and revealing happen in one transaction on purpose: a window in which the
     * round is locked but not yet resolved is a window in which somebody could read the
     * amounts and act on them.
     */
    public function lockAndReveal(AuctionClosedBidRound $round, ?User $actor = null, bool $force = false): array
    {
        return DB::transaction(function () use ($round, $actor, $force) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->locked_at !== null) {
                return ['handled' => false, 'message' => 'That round is already closed.', 'round' => $round];
            }

            if ($round->state !== AuctionClosedBidRound::STATE_COLLECTING) {
                return ['handled' => false, 'message' => 'That round is not collecting bids.', 'round' => $round];
            }

            $standing = $round->entries()->standing()->get();

            /*
             * Refuse a manual lock with nothing entered.
             *
             * Locking an empty round resolves it to `no_entries`, which is a real outcome but
             * a terminal one — the round is over and the only ways out are awarding the
             * open-bid leader or marking the player unsold. Pressing Lock moments after Start
             * therefore threw the round away, and the only feedback was "No team entered the
             * sealed round", which reads as a fault rather than as "you have not entered any
             * amounts yet".
             *
             * It matters most offline, where the teams are in the room and CANNOT submit for
             * themselves — the organizer types every amount, so an empty board is always
             * premature rather than a genuine absence of interest.
             *
             * The timer-expiry path passes $force, so a round that genuinely ran its course
             * with no bids still resolves to no_entries exactly as before.
             */
            /*
             * Only the FIRST round. In a re-bid, zero standing entries has defined meaning —
             * the required teams did not come back, so the tie goes to a lot — and refusing
             * the lock there blocked a legitimate path to `awaiting_lot`.
             */
            if (! $force && $standing->isEmpty() && (int) $round->round_number === 1) {
                $auction = $round->auction;
                $expired = $auction->closedBidRoundTimerState($round)['expired'] ?? false;

                if (! $expired) {
                    return [
                        'handled' => false,
                        'message' => $auction->open_bid_mode === 'offline'
                            ? 'No amounts have been entered yet. Type each team\'s sealed amount on this board first — offline, the teams cannot submit for themselves.'
                            : 'No team has submitted yet. Wait for the teams, or enter their amounts here, before locking the round.',
                        'round' => $round,
                    ];
                }
            }

            // A team that was required to bid again and did not has left the tie. Its
            // earlier amount is deliberately NOT carried forward — doing so would
            // recreate the same tie every round and the ladder would never terminate.
            $round->entries()
                ->required()
                ->whereNull('submitted_at')
                ->update(['state' => AuctionClosedBidEntry::STATE_NO_ENTRY]);

            $updates = [
                'locked_at' => now(),
                'locked_by' => $actor?->id,
                'revealed_at' => now(),
            ];

            if ($standing->isEmpty()) {
                // A tie-break round nobody came back for is not the same as a player
                // nobody wanted. The teams that tied still want the player — they just
                // failed to re-bid — so the contest goes to the lot rather than sending a
                // genuinely contested player to unsold.
                $inherited = $round->parent?->tied_team_ids ?? [];

                if ($round->round_number > 1 && $inherited !== []) {
                    $updates['state'] = AuctionClosedBidRound::STATE_AWAITING_LOT;
                    $updates['tie_amount'] = $round->parent->tie_amount;
                    $updates['tied_team_ids'] = $inherited;
                    $round->update($updates);

                    return ['handled' => true, 'message' => 'Nobody re-bid — the tie goes to a lot.', 'round' => $round->fresh()];
                }

                $updates['state'] = AuctionClosedBidRound::STATE_NO_ENTRIES;
                $round->update($updates);

                return ['handled' => true, 'message' => 'No team entered the round.', 'round' => $round->fresh()];
            }

            $top = (float) $standing->max('amount');
            $tied = $standing->filter(fn (AuctionClosedBidEntry $e) => (float) $e->amount === $top)->values();

            if ($tied->count() === 1) {
                $updates['state'] = AuctionClosedBidRound::STATE_REVEALED;
                $updates['winner_team_id'] = $tied->first()->actual_team_id;
                $updates['winning_amount'] = $top;
                $updates['resolution'] = AuctionClosedBidRound::RESOLUTION_HIGHEST;
                $round->update($updates);

                return ['handled' => true, 'message' => 'Highest sealed bid found.', 'round' => $round->fresh()];
            }

            $updates['tie_amount'] = $top;
            $updates['tied_team_ids'] = $tied->pluck('actual_team_id')->all();
            $updates['state'] = $round->isFinalRound()
                ? AuctionClosedBidRound::STATE_AWAITING_LOT
                : AuctionClosedBidRound::STATE_TIE;
            $round->update($updates);

            return [
                'handled' => true,
                'message' => sprintf('%d teams tied at %s.', $tied->count(), format_points($top)),
                'round' => $round->fresh(),
            ];
        });
    }

    /**
     * Open the next round of a tie.
     *
     * The tied teams MUST bid again; teams that bid below them MAY opt in. Teams that
     * declined or withdrew themselves are not invited — they chose to leave. A team the
     * admin withdrew IS invited, because an admin withdrawal is a correction rather than
     * the team's own decision.
     *
     * The invitation states are written onto the child round's entries rather than
     * worked out per request, so "must" and "may" are facts on the record instead of an
     * inference that could differ between two screens.
     */
    public function startRebid(AuctionClosedBidRound $round, ?User $actor = null): array
    {
        return DB::transaction(function () use ($round, $actor) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->state !== AuctionClosedBidRound::STATE_TIE) {
                return ['handled' => false, 'message' => 'That round is not tied.', 'round' => $round];
            }

            if ($round->isFinalRound()) {
                return ['handled' => false, 'message' => 'The re-bid rounds are used up — draw a lot.', 'round' => $round];
            }

            $auction = $round->auction;
            $tied = array_map('intval', $round->tied_team_ids ?? []);
            // Strictly above the tied amount, enforced by the same floor check as any
            // other bid rather than by asking politely in the copy.
            $floor = $this->increments->nextLegalAbove($auction, (float) $round->tie_amount);

            $child = AuctionClosedBidRound::firstOrCreate(
                [
                    'auction_player_id' => $round->auction_player_id,
                    'attempt_no' => $round->attempt_no,
                    'round_number' => $round->round_number + 1,
                ],
                [
                    'auction_id' => $auction->id,
                    'parent_round_id' => $round->id,
                    'state' => AuctionClosedBidRound::STATE_COLLECTING,
                    'floor' => $floor,
                    'step' => $round->step,
                    'max_pct_of_budget' => $round->max_pct_of_budget,
                    'leader_team_id' => $round->leader_team_id,
                    'leader_amount' => $round->leader_amount,
                    'timer_seconds' => $round->timer_seconds,
                    'timer_started_at' => now(),
                    'opened_at' => now(),
                    'opened_by' => $actor?->id,
                ]
            );

            foreach ($round->entries as $entry) {
                $isTied = in_array((int) $entry->actual_team_id, $tied, true);

                // Left of their own accord — not invited back.
                $leftVoluntarily = $entry->state === AuctionClosedBidEntry::STATE_DECLINED
                    || ($entry->isWithdrawn() && ! $entry->wasWithdrawnByAdmin());

                if (! $isTied && $leftVoluntarily) {
                    continue;
                }

                $purse = $this->pools->teamPurseState($auction, $entry->actual_team_id);

                AuctionClosedBidEntry::updateOrCreate(
                    [
                        'auction_closed_bid_round_id' => $child->id,
                        'actual_team_id' => $entry->actual_team_id,
                    ],
                    [
                        'auction_id' => $auction->id,
                        'state' => $isTied
                            ? AuctionClosedBidEntry::STATE_MUST_REBID
                            : AuctionClosedBidEntry::STATE_MAY_OPT_IN,
                        'required' => $isTied,
                        'ceiling_at_entry' => $this->cap($purse['sealed_max_bid']),
                        'per_player_cap_at_entry' => $this->cap($purse['per_player_cap']),
                        'reserve_at_entry' => $purse['reserve'],
                        'slots_remaining_at_entry' => $purse['slots_remaining'],
                    ]
                );
            }

            $round->auctionPlayer->update(['closed_bid_round_id' => $child->id]);

            return [
                'handled' => true,
                'message' => sprintf('Round %d is open — bids must exceed %s.', $child->round_number, format_points((float) $round->tie_amount)),
                'round' => $child->fresh(),
            ];
        });
    }

    /**
     * Settle a tie by drawing a lot.
     *
     * The draw itself lives in ClosedBidLotService and takes nothing from the caller —
     * no seed, no index, no winner. Everything it used is recorded so the result can be
     * recomputed and checked afterwards.
     */
    public function drawLot(AuctionClosedBidRound $round, ?User $actor = null): array
    {
        $result = DB::transaction(function () use ($round, $actor) {
            $round = AuctionClosedBidRound::lockForUpdate()->find($round->id);

            if ($round->lot_drawn_at !== null) {
                return ['handled' => false, 'message' => 'A lot has already been drawn for this round.', 'round' => $round];
            }

            if (! in_array($round->state, [
                AuctionClosedBidRound::STATE_AWAITING_LOT,
                AuctionClosedBidRound::STATE_TIE,
            ], true)) {
                return ['handled' => false, 'message' => 'There is no tie to draw for.', 'round' => $round];
            }

            $candidates = array_map('intval', $round->tied_team_ids ?? []);

            if ($candidates === []) {
                return ['handled' => false, 'message' => 'No tied teams to draw between.', 'round' => $round];
            }

            $draw = $this->lots->draw($round, $candidates);

            $round->update([
                'state' => AuctionClosedBidRound::STATE_AWAITING_LOT,
                'lot_algorithm' => $draw['algorithm'],
                'lot_seed' => $draw['seed'],
                'lot_candidates' => $draw['candidates'],
                'lot_winner_team_id' => $draw['winner_team_id'],
                'lot_drawn_at' => now(),
            ]);

            $this->undo->record(
                $round->auction,
                AuctionActionLog::ACTION_CLOSED_LOT,
                $round->auctionPlayer,
                [
                    'round_id' => $round->id,
                    'algorithm' => $draw['algorithm'],
                    'seed' => $draw['seed'],
                    'candidates' => $draw['candidates'],
                    'winner_team_id' => $draw['winner_team_id'],
                ],
                sprintf('Lot drawn between %d teams', count($draw['candidates']))
            );

            return ['handled' => true, 'draw' => $draw, 'round' => $round->fresh()];
        });

        if (! ($result['handled'] ?? false)) {
            return $result;
        }

        $round = $result['round'];
        $team = ActualTeam::find($result['draw']['winner_team_id']);

        if (! $team) {
            return ['handled' => false, 'message' => 'The drawn team no longer exists.', 'round' => $round];
        }

        $award = $this->awardTo(
            $round,
            $team,
            (float) $round->tie_amount,
            AuctionClosedBidRound::RESOLUTION_LOT,
            $actor
        );

        // The draw itself is recorded either way; only the award can fail here (a budget
        // can move between the reveal and the draw), and the record shows what happened.
        return $award + ['draw' => $result['draw']];
    }

    /**
     * Settle a tie by the organizer's own decision.
     *
     * Restricted to the tied teams, and a reason is required. An override nobody has to
     * explain is indistinguishable from an arbitrary one, and this is exactly the moment
     * somebody will later ask why.
     */
    public function resolveManual(
        AuctionClosedBidRound $round,
        int $teamId,
        string $reason,
        ?User $actor = null
    ): array {
        $round = $round->fresh();

        if (! in_array($round->state, [
            AuctionClosedBidRound::STATE_TIE,
            AuctionClosedBidRound::STATE_AWAITING_LOT,
        ], true)) {
            return ['handled' => false, 'message' => 'There is no tie to resolve.'];
        }

        $tied = array_map('intval', $round->tied_team_ids ?? []);

        if (! in_array($teamId, $tied, true)) {
            return ['handled' => false, 'message' => 'That team is not one of the tied teams.'];
        }

        $team = ActualTeam::find($teamId);

        if (! $team) {
            return ['handled' => false, 'message' => 'That team no longer exists.'];
        }

        $result = $this->awardTo(
            $round,
            $team,
            (float) $round->tie_amount,
            AuctionClosedBidRound::RESOLUTION_MANUAL,
            $actor
        );

        if ($result['handled'] ?? false) {
            $this->undo->record(
                $round->auction,
                AuctionActionLog::ACTION_CLOSED_LOT,
                $round->auctionPlayer,
                [
                    'round_id' => $round->id,
                    'winner_team_id' => $teamId,
                    'resolution' => AuctionClosedBidRound::RESOLUTION_MANUAL,
                    'reason' => $reason,
                ],
                sprintf('Tie resolved manually for %s: %s', $team->name, $reason)
            );
        }

        return $result;
    }

    /**
     * Give the player to the round's winner.
     *
     * Takes no team and no amount from the caller: the winner is derived from the
     * standing entries. The previous sealed-award endpoint accepted both from the
     * request and checked neither against any bid that was actually placed.
     */
    public function award(AuctionClosedBidRound $round, ?User $actor = null): array
    {
        $round = $round->fresh();

        if ($round->state === AuctionClosedBidRound::STATE_AWARDED) {
            return ['handled' => false, 'message' => 'That player has already been awarded.'];
        }

        $winnerId = $round->winner_team_id;
        $amount = $round->winning_amount !== null ? (float) $round->winning_amount : null;

        // Nothing resolved yet — fall back to the standing board, with a total order so
        // the answer cannot depend on what the database happens to return.
        if (! $winnerId) {
            $best = $round->entries()->standing()
                ->orderByDesc('amount')
                ->orderBy('submitted_at')
                ->orderBy('actual_team_id')
                ->first();

            if (! $best) {
                return ['handled' => false, 'message' => 'No standing bid to award.'];
            }

            $winnerId = $best->actual_team_id;
            $amount = (float) $best->amount;
        }

        $team = ActualTeam::find($winnerId);

        if (! $team) {
            return ['handled' => false, 'message' => 'The winning team no longer exists.'];
        }

        return $this->awardTo($round, $team, (float) $amount, AuctionClosedBidRound::RESOLUTION_HIGHEST, $actor);
    }

    /**
     * The one way a sealed round hands a player over.
     *
     * Goes through AuctionSaleService so the roster pivot, the team-user row, the Spatie
     * role and the welcome card all happen — the old sealed award wrote none of those
     * until it was fixed, and this must not regress to a bespoke copy.
     */
    public function awardTo(
        AuctionClosedBidRound $round,
        ActualTeam $team,
        float $amount,
        string $resolution,
        ?User $actor = null
    ): array {
        $auctionPlayer = $round->auctionPlayer;
        $auction = $round->auction;

        if ($auctionPlayer->status === 'sold') {
            return ['handled' => false, 'message' => 'That player has already been sold.'];
        }

        // Budgets move; re-check rather than trusting what the round recorded earlier.
        if (! $this->pools->canAffordWithReserve($auction, $team->id, $amount)) {
            return [
                'handled' => false,
                'message' => $this->pools->reserveBlockedMessage($auction, $team->id, $amount, $team->name),
            ];
        }

        // The sealed amounts live in their own tables, so the bid log gets one audit row
        // here — which is what keeps undo-a-sale and every bid-derived total working.
        $auditBid = AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer->id,
            'player_id' => $auctionPlayer->player_id,
            'team_id' => $team->id,
            // auction_bids.user_id is NOT NULL, and the audit row has to attribute the
            // award to somebody.
            'user_id' => $actor?->id ?? auth()->id(),
            'amount' => $amount,
            'bid_source' => 'offline',
        ]);

        $snapshot = $this->sales->applySale($auctionPlayer, $team, $amount);

        $this->undo->record(
            $auction,
            AuctionActionLog::ACTION_SELL,
            $auctionPlayer,
            $snapshot + [
                'amount' => $amount,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'audit_bid_id' => $auditBid->id,
                'round_id' => $round->id,
            ],
            sprintf('Sealed round: awarded to %s for %s', $team->name, format_points($amount))
        );

        $round->update([
            'state' => AuctionClosedBidRound::STATE_AWARDED,
            'resolution' => $resolution,
            'winner_team_id' => $team->id,
            'winning_amount' => $amount,
            'resolved_at' => now(),
            'resolved_by' => $actor?->id,
        ]);

        return [
            'handled' => true,
            'message' => sprintf('%s wins %s for %s.', $team->name, $auctionPlayer->player->name ?? 'the player', format_points($amount)),
            'round' => $round->fresh(),
        ];
    }

    /**
     * Nudge a team's sealed amount from the organizer's desk.
     *
     * Runs the same three checks a team's own submission runs — legal step, at or above
     * the floor, within both ceilings — by going through submit(). A custom value of
     * 1,234,567 is refused here exactly as it would be on the team's screen.
     *
     * Refused once the round is revealed: adjusting a board everybody has seen is
     * rewriting history, and the tool for that is a recorded manual resolution.
     *
     * @param  string  $direction  'up', 'down', or '' when an explicit amount is given
     */
    /**
     * The highest amount standing on the board right now, or 0.0 when nobody has bid.
     *
     * standing() is the single definition of "a bid that counts" — submitted and not
     * withdrawn — so a withdrawn team's amount never sets the pace for the next raise.
     */
    private function topStandingAmount(AuctionClosedBidRound $round): float
    {
        return (float) ($round->entries()->standing()->max('amount') ?? 0.0);
    }

    public function adjust(
        AuctionClosedBidEntry $entry,
        ?float $amount,
        string $direction = '',
        ?User $actor = null
    ): array {
        $round = $entry->round;

        if ($round->isRevealed() || $round->isTerminal()) {
            return ['handled' => false, 'message' => 'That round has been revealed — resolve it instead.'];
        }

        if ($entry->isWithdrawn()) {
            return ['handled' => false, 'message' => 'That team has withdrawn — reinstate it first.'];
        }

        $step = (float) $round->step;

        /*
         * Where a raise starts from.
         *
         * A team with an amount steps from its own. A team with none steps from the top of
         * the board — the way an auctioneer works a room, each raise going over the last
         * one called, not over the opening figure. Starting every new team at the floor
         * meant the organizer had to press + once per increment to catch up with the
         * standing bid: ten presses to reach 9M on a 100K step, which is what
         * "adjusted x10" on the board was recording.
         *
         * Computed here rather than handed to the panel on purpose. Amounts are withheld
         * from the organizer's board until the round is revealed because that board is
         * routinely on a projector, and shipping the current top so the client could do
         * this arithmetic would put the leading sealed bid on the wall.
         */
        $current = $entry->amount !== null
            ? (float) $entry->amount
            : max((float) $round->floor, $this->topStandingAmount($round));

        $target = match ($direction) {
            // Snap onto the grid as it steps, so a +/- press also rescues an amount that
            // is somehow off it.
            'up' => $this->increments->snapUpToStep($round->auction, $current + $step),
            'down' => max((float) $round->floor, $current - $step),
            default => $amount,
        };

        if ($target === null) {
            return ['handled' => false, 'message' => 'No amount given.'];
        }

        $team = $entry->team;

        if (! $team) {
            return ['handled' => false, 'message' => 'That team no longer exists.'];
        }

        return $this->submit($round, $team, (float) $target, $actor, AuctionClosedBidEntry::ROLE_ADMIN);
    }

    /**
     * Settle a round nobody entered.
     *
     * The organizer chooses, because both answers are defensible: the player was already
     * at the threshold with a leading team when the sealed round opened, so awarding
     * that standing bid loses nothing — but so does treating the sealed round as having
     * superseded the open one.
     *
     * @param  string  $choice  'award_leader' or 'unsold'
     */
    public function resolveNoEntries(AuctionClosedBidRound $round, string $choice, ?User $actor = null): array
    {
        $round = $round->fresh();

        if ($round->state !== AuctionClosedBidRound::STATE_NO_ENTRIES) {
            return ['handled' => false, 'message' => 'That round has entrants.'];
        }

        if ($choice === 'award_leader') {
            if (! $round->leader_team_id) {
                return [
                    'handled' => false,
                    'message' => 'There was no leading team when the sealed round opened.',
                ];
            }

            $team = ActualTeam::find($round->leader_team_id);

            if (! $team) {
                return ['handled' => false, 'message' => 'That team no longer exists.'];
            }

            return $this->awardTo(
                $round,
                $team,
                (float) $round->floor,
                AuctionClosedBidRound::RESOLUTION_LEADER_AT_THRESHOLD,
                $actor
            );
        }

        $round->update([
            'state' => AuctionClosedBidRound::STATE_UNSOLD,
            'resolution' => AuctionClosedBidRound::RESOLUTION_UNSOLD,
            'resolved_at' => now(),
            'resolved_by' => $actor?->id,
        ]);

        return ['handled' => true, 'message' => 'Sent to the unsold pool.', 'round' => $round->fresh()];
    }

    /** The team's row for this round, created on demand. */
    private function entryFor(AuctionClosedBidRound $round, ActualTeam $team): AuctionClosedBidEntry
    {
        return AuctionClosedBidEntry::firstOrCreate(
            [
                'auction_closed_bid_round_id' => $round->id,
                'actual_team_id' => $team->id,
            ],
            [
                'auction_id' => $round->auction_id,
                'state' => AuctionClosedBidEntry::STATE_INVITED,
            ]
        );
    }

    /** Open tournaments report PHP_FLOAT_MAX, which does not survive JSON encoding. */
    private function cap(float $value): float
    {
        return $value >= 1.0e15 ? 1.0e15 : $value;
    }
}
