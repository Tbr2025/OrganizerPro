<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Models\AuctionPlayer;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * One player's whole trail through a tournament's auctions.
 *
 * The list view answers "how did this player arrive and what did they cost". This answers the
 * question that follows it — and it is the question asked when a price is disputed: who bid what,
 * when, in what order, which bids were retracted, what the sealed round actually contained, and
 * who pressed sell.
 *
 * All of that is already recorded and almost none of it has ever been shown. The open-bid trail
 * appears on one auction's report; the sealed rounds — every team's submitted amount, the
 * withdrawals, the tie set, the lot draw and its seed — have never had a screen anywhere in the
 * application.
 */
class PlayerHistoryTimeline
{
    public function __construct(private readonly PlayerHistoryQuery $history)
    {
    }

    /**
     * Every auction of this tournament the player appears in, newest first, each with its own
     * timeline and sealed rounds.
     *
     * @return list<array<string, mixed>>
     */
    public function for(Tournament $tournament, Player $player): array
    {
        $rows = AuctionPlayer::query()
            ->where('player_id', $player->id)
            ->whereHas('auction', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with(['auction', 'pool:id,name', 'sourcePool:id,name',
                'team:id,name,team_logo', 'soldToTeam:id,name,team_logo'])
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $this->history->decorate($rows);

        // The tournament squad row, which a sale writes. Read once rather than per auction.
        $squadJoinedAt = $this->squadJoinedAt($tournament, $player);

        $sections = [];

        foreach ($rows as $row) {
            $rounds = $this->sealedRounds($row);

            $events = array_merge(
                $this->entryEvents($row),
                $this->bidEvents($row),
                $this->actionEvents($row),
                $this->sealedEvents($row, $rounds),
            );

            /*
             * The squad row belongs to the auction that produced it — the sale — rather than to
             * every auction the player ever appeared in.
             */
            if ($squadJoinedAt && $row->status === 'sold') {
                $events[] = $this->event($row, $squadJoinedAt, 'squad', 'Joined the tournament squad', [
                    'team' => $row->holding_team,
                ]);
                $squadJoinedAt = null;
            }

            $events = $this->inOrder($events);

            $sections[] = [
                'auction' => $row->auction,
                'row' => $row,
                'events' => $events,
                'rounds' => $rounds,
            ];
        }

        return $sections;
    }

    /**
     * How the player entered this auction, and where they stand now.
     *
     * There is no per-move pool history in the schema — `auction_pool_id` holds where they are
     * and `source_pool_id` where they were bid, with no record of the moves between. So this
     * states what is actually known rather than implying a trail of reassignments.
     *
     * @return list<array<string, mixed>>
     */
    private function entryEvents(AuctionPlayer $row): array
    {
        $pool = $row->origin_pool;

        $label = $row->is_retained
            ? 'Kept by the team before the auction'
            : ($pool ? 'Entered the auction in ' . $pool->name : 'Entered the auction');

        $events = [$this->event($row, $row->created_at, 'entered', $label, [
            'team' => $row->is_retained ? $row->team : null,
            'amount' => $row->is_retained ? (float) $row->retained_price : (float) $row->base_price,
            'note' => $row->lot_number ? 'Lot ' . $row->lot_number : null,
        ])];

        /*
         * An unsold player's pool changed under them: they were moved into the shared unsold
         * pile, which is why `source_pool_id` exists at all. Worth saying, because the row now
         * reads as belonging to a pool nobody bid in.
         */
        if ($row->source_pool_id && $row->auction_pool_id && $row->auction_pool_id !== $row->source_pool_id) {
            $events[] = $this->event($row, $row->updated_at, 'pool', 'Moved to ' . ($row->pool->name ?? 'the unsold pool'), [
                'note' => 'from ' . ($row->sourcePool->name ?? 'their pool'),
            ]);
        }

        return $events;
    }

    /**
     * Every bid, including the retracted ones.
     *
     * Read from `auction_bids` rather than from the action log, which also records a row per
     * bid: the bid table carries the team, the amount, whether it came from the floor or a
     * phone, and whether it was later voided. Taking both would list every bid twice.
     *
     * Void bids are shown, not hidden. A retracted bid is part of what happened, and a trail
     * that quietly drops one cannot be reconciled against the price it moved.
     *
     * @return list<array<string, mixed>>
     */
    private function bidEvents(AuctionPlayer $row): array
    {
        $bids = AuctionBid::query()
            ->where('auction_player_id', $row->id)
            ->with(['team:id,name,team_logo', 'user:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $events = [];
        $previous = null;

        foreach ($bids as $bid) {
            $events[] = $this->event(
                $row,
                $bid->created_at,
                'bid',
                $bid->is_void ? 'Bid retracted' : 'Bid',
                [
                    'team' => $bid->team,
                    'amount' => (float) $bid->amount,
                    'actor' => $bid->user?->name,
                    'void' => (bool) $bid->is_void,
                    // How long the room took to answer the previous bid — the figure the
                    // auction report calls the gap, and the one that shows a bidding war.
                    'gap' => $previous ? $bid->created_at->diffInSeconds($previous) : null,
                    'note' => $bid->bid_source === 'offline' ? 'in the room' : null,
                ]
            );

            $previous = $bid->created_at;
        }

        return $events;
    }

    /**
     * The decisions: sold, passed, skipped, allotted, and the sealed-round operations.
     *
     * `description` is written at the time ("Sold to X for Y"), so it is preferred over anything
     * reconstructed here. Undone actions stay visible and are marked as undone — the log is the
     * record of what was done, and an action that was reversed still happened.
     *
     * @return list<array<string, mixed>>
     */
    private function actionEvents(AuctionPlayer $row): array
    {
        $logs = AuctionActionLog::query()
            ->where('auction_player_id', $row->id)
            // Bids come from the bid table; taking them here as well lists each one twice.
            ->where('action', '!=', AuctionActionLog::ACTION_BID)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $events = [];

        foreach ($logs as $log) {
            $events[] = $this->event(
                $row,
                $log->created_at,
                $log->action,
                $log->description ?: $this->actionLabel($log->action),
                [
                    'actor' => $log->user?->name,
                    'undone' => $log->undone_at !== null,
                    'note' => $log->undone_at
                        ? 'undone ' . $log->undone_at->copy()->setTimezone(array_key_first(PlayerHistoryQuery::zones()))->format('d M, h:i A')
                        : null,
                ]
            );
        }

        /*
         * A sale with no log row. `auction_action_logs` only started recording in August 2026, so
         * an older auction has a sold player and no sell event — and a timeline that ends at the
         * last bid reads as though the sale never happened. `sold_at` is the fallback, backfilled
         * from the winning bid where the log is absent.
         */
        $hasSale = $logs->contains(fn ($l) => in_array($l->action, [
            AuctionActionLog::ACTION_SELL, AuctionActionLog::ACTION_ALLOT,
            AuctionActionLog::ACTION_CLOSED_BID,
        ], true) && $l->undone_at === null);

        if ($row->status === 'sold' && ! $hasSale && $row->sold_at) {
            $events[] = $this->event($row, $row->sold_at, AuctionActionLog::ACTION_SELL, 'Sold', [
                'team' => $row->soldToTeam,
                'amount' => (float) $row->final_price,
                'note' => 'recorded before the action log existed',
            ]);
        }

        return $events;
    }

    /**
     * Sealed rounds, with every team's amount.
     *
     * These amounts are sealed while a round is live and are never broadcast — but after the
     * fact they are the record of how a contested player was decided, and this is the first
     * screen in the application to show them. Admin-only, behind `tournament.view`.
     *
     * @return list<array<string, mixed>>
     */
    private function sealedRounds(AuctionPlayer $row): array
    {
        $rounds = AuctionClosedBidRound::query()
            ->where('auction_player_id', $row->id)
            ->with([
                'entries' => fn ($q) => $q->with(['team:id,name,team_logo', 'submittedBy:id,name'])
                    ->orderByDesc('amount'),
                'winnerTeam:id,name',
                'leaderTeam:id,name',
            ])
            ->orderBy('attempt_no')
            ->orderBy('round_number')
            ->get();

        $auction = $row->auction;

        return $rounds->map(fn (AuctionClosedBidRound $round) => [
            'round' => $round,
            'entries' => $round->entries->map(fn (AuctionClosedBidEntry $entry) => [
                'entry' => $entry,
                'team' => $entry->team,
                'amount_label' => $entry->amount !== null && (float) $entry->amount > 0
                    ? ($auction ? $auction->formatAmount($entry->amount) : format_points($entry->amount))
                    : '—',
                'state_label' => $this->entryStateLabel($entry->state),
                // An admin pulling a team's bid is a different fact from the team withdrawing it.
                'withdrawn_by_admin' => $entry->isWithdrawn() && $entry->wasWithdrawnByAdmin(),
            ])->all(),
            'winning_label' => $round->winning_amount !== null
                ? ($auction ? $auction->formatAmount($round->winning_amount) : format_points($round->winning_amount))
                : null,
            'resolution_label' => $this->resolutionLabel($round->resolution),
            'times' => $this->history->times($round->revealed_at ?: $round->resolved_at ?: $round->opened_at),
            // A draw is reproducible from its seed, which is the point of recording it.
            'lot' => $round->lot_drawn_at ? [
                'algorithm' => $round->lot_algorithm,
                'seed' => $round->lot_seed,
                'candidates' => $round->lot_candidates,
                'times' => $this->history->times($round->lot_drawn_at),
            ] : null,
        ])->all();
    }

    /**
     * The sealed rounds as timeline entries, so they sit in order among the open bids.
     *
     * @param  list<array<string, mixed>>  $rounds
     * @return list<array<string, mixed>>
     */
    private function sealedEvents(AuctionPlayer $row, array $rounds): array
    {
        $events = [];

        foreach ($rounds as $entry) {
            /** @var AuctionClosedBidRound $round */
            $round = $entry['round'];
            $name = 'Sealed round ' . $round->round_number;

            if ($round->opened_at) {
                $events[] = $this->event($row, $round->opened_at, 'sealed', $name . ' opened', [
                    'note' => $round->floor !== null ? 'floor ' . format_points($round->floor) : null,
                ]);
            }

            if ($round->revealed_at) {
                $events[] = $this->event($row, $round->revealed_at, 'sealed', $name . ' revealed', [
                    'note' => count($entry['entries']) . ' ' . \Illuminate\Support\Str::plural('entry', count($entry['entries'])),
                ]);
            }

            if ($round->lot_drawn_at) {
                // The winner is named on the round's resolution event below; a draw records the
                // tie it was breaking, and the seed that makes it reproducible.
                $events[] = $this->event($row, $round->lot_drawn_at, 'sealed', $name . ' decided by lot', [
                    'note' => 'tie at ' . format_points($round->tie_amount),
                ]);
            }

            if ($round->resolved_at) {
                $events[] = $this->event($row, $round->resolved_at, 'sealed', $name . ' resolved', [
                    'team' => $round->winnerTeam,
                    'amount' => $round->winning_amount !== null ? (float) $round->winning_amount : null,
                    'note' => $entry['resolution_label'],
                ]);
            }
        }

        return $events;
    }

    /** When the sale put this player on a tournament squad, if it did. */
    private function squadJoinedAt(Tournament $tournament, Player $player): ?\Illuminate\Support\Carbon
    {
        $row = DB::table('player_actual_team_tournament')
            ->where('player_id', $player->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        return $row?->created_at ? \Illuminate\Support\Carbon::parse($row->created_at) : null;
    }

    /**
     * One timeline entry, with its money formatted in the auction's own unit and its time in
     * every zone the report prints.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function event(AuctionPlayer $row, $at, string $kind, string $label, array $extra = []): array
    {
        $auction = $row->auction;
        $amount = $extra['amount'] ?? null;

        return array_merge([
            'at' => $at,
            'times' => $this->history->times($at),
            'kind' => $kind,
            'label' => $label,
            'team' => null,
            'amount' => $amount,
            'amount_label' => $amount !== null && (float) $amount > 0
                ? ($auction ? $auction->formatAmount($amount) : format_points($amount))
                : null,
            'actor' => null,
            'note' => null,
            'void' => false,
            'undone' => false,
            'gap' => null,
        ], $extra);
    }

    /**
     * Oldest first, and stable.
     *
     * Several events can share a second — a sale and the squad row it writes are the same
     * instant — so a plain sort by time would order them differently on each render.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function inOrder(array $events): array
    {
        $events = array_values(array_filter($events, fn ($e) => $e['at'] !== null));

        usort($events, function ($a, $b) {
            $delta = $a['at']->getTimestamp() <=> $b['at']->getTimestamp();

            return $delta !== 0 ? $delta : $this->weight($a['kind']) <=> $this->weight($b['kind']);
        });

        return $events;
    }

    /** Within one second, events read in the order they must have happened. */
    private function weight(string $kind): int
    {
        return match ($kind) {
            'entered' => 0,
            'pool' => 1,
            'bid' => 2,
            'sealed' => 3,
            AuctionActionLog::ACTION_SELL, AuctionActionLog::ACTION_ALLOT,
            AuctionActionLog::ACTION_CLOSED_BID => 4,
            'squad' => 5,
            default => 3,
        };
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            AuctionActionLog::ACTION_SELL => 'Sold',
            AuctionActionLog::ACTION_PASS => 'Passed — nobody bid',
            AuctionActionLog::ACTION_SKIP => 'Skipped',
            AuctionActionLog::ACTION_ALLOT => 'Allotted after the auction',
            AuctionActionLog::ACTION_CLOSED_BID => 'Sealed bid',
            AuctionActionLog::ACTION_CLOSED_ADJUST => 'Sealed bid adjusted',
            AuctionActionLog::ACTION_CLOSED_WITHDRAW => 'Sealed bid withdrawn',
            AuctionActionLog::ACTION_CLOSED_LOT => 'Tie decided by lot',
            AuctionActionLog::ACTION_CLOSED_REVEAL => 'Sealed round revealed',
            'auto_assign' => 'Assigned to a pool automatically',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    private function entryStateLabel(?string $state): string
    {
        return match ($state) {
            AuctionClosedBidEntry::STATE_SUBMITTED => 'submitted',
            AuctionClosedBidEntry::STATE_ACCEPTED => 'accepted, no amount',
            AuctionClosedBidEntry::STATE_DECLINED => 'declined',
            AuctionClosedBidEntry::STATE_WITHDRAWN => 'withdrawn',
            AuctionClosedBidEntry::STATE_MUST_REBID => 'must rebid',
            AuctionClosedBidEntry::STATE_MAY_OPT_IN => 'may opt in',
            AuctionClosedBidEntry::STATE_NO_ENTRY => 'no entry',
            AuctionClosedBidEntry::STATE_INVITED => 'invited',
            default => (string) $state,
        };
    }

    private function resolutionLabel(?string $resolution): ?string
    {
        return match ($resolution) {
            AuctionClosedBidRound::RESOLUTION_HIGHEST => 'highest sealed bid',
            AuctionClosedBidRound::RESOLUTION_LOT => 'decided by lot',
            AuctionClosedBidRound::RESOLUTION_MANUAL => 'decided by the organizer',
            AuctionClosedBidRound::RESOLUTION_LEADER_AT_THRESHOLD => 'leader at the threshold',
            AuctionClosedBidRound::RESOLUTION_UNSOLD => 'nobody bid',
            AuctionClosedBidRound::RESOLUTION_ABANDONED => 'abandoned',
            default => null,
        };
    }
}
