<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionOperator;
use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\ActualTeam;
use App\Models\Player;
use App\Events\AuctionStatusUpdate;
use App\Events\PlayerOnBid;
use App\Events\PlayerSoldEvent;
use App\Notifications\GeneralNotification;
use App\Jobs\FlushAuctionEmails;
use App\Models\AuctionPendingEmail;
use App\Services\Auction\AuctionMailService;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionSaleService;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\BidIncrementService;
use App\Services\Export\AuctionSnapshotExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuctionOrganizerController extends Controller
{
    public function __construct(
        private readonly AuctionPoolService $pools,
        private readonly AuctionSaleService $sales,
        private readonly AuctionUndoService $undo,
        private readonly BidIncrementService $increments,
        private readonly AuctionMailService $mail,
    ) {
    }

    /**
     * Every team in the auction with its purse, squad count and bidding eligibility.
     *
     * This replaced three byte-identical copies of a budget calculation that ignored
     * per-team allocations and retained-player cost, plus a fourth fallback formula
     * in the panel's JavaScript. All figures now come from AuctionPoolService, which
     * is also what the sell-side checks use, so the panel can never show a team as
     * able to afford something the server will refuse.
     *
     * @return \Illuminate\Support\Collection<int, ActualTeam>
     */
    private function teamsWithPurse(Auction $auction, ?AuctionPlayer $currentPlayer = null)
    {
        // What the next raise would cost — used to grey out teams priced out of the
        // player currently on the block.
        $nextBid = $currentPlayer
            // Opening bid = the base price itself, not base + increment. See nextBidForPlayer().
            ? $this->increments->nextBidForPlayer($auction, $currentPlayer)
            : null;

        /*
         * The teams actually in this auction — approved registrations only.
         *
         * This read ActualTeam::forTournament() directly, which is every team row in the
         * tournament. So the control panel's bubbles and its offline team picker listed all
         * seven of the tournament's teams while the broadcast ticker, which goes through
         * participatingTeams(), listed the five that had been approved. Two screens in the
         * same room disagreeing about who is even in the auction, and the panel was the one
         * you could bid from.
         */
        $teams = $this->pools->participatingTeams($auction);

        /*
         * One batched read for every team, not five queries each.
         *
         * This ran teamPurseState() per team, and pollState() calls this method on a
         * two-second poll — seven teams meant thirty-five queries every two seconds while
         * the auction was also writing bids to the same table. poll-state was taking
         * between one and eight seconds on the live panel, and a slow poll is what stacks
         * requests until a browser runs out of connections.
         */
        $purses = $this->pools->teamPurseStates(
            $auction,
            $teams->pluck('id')->all(),
            $nextBid
        );

        return $teams
            ->map(function (ActualTeam $team) use ($purses) {
                $state = $purses[$team->id];

                // The column is `team_logo`; the panels were binding a non-existent
                // `logo_path`, so no team logo ever rendered — every team fell back to
                // its initials. Expose the resolved URL explicitly.
                $team->logo_url = $team->team_logo_url;

                $team->players_bought = $state['slots_filled'];
                $team->total_spent = $state['spent'];
                $team->remaining_budget = $this->cap($state['remaining']);

                // The configured total, and the purse left once retentions are paid.
                // Without `allocated` every screen fell back to the auction-wide cap and
                // a team with its own budget saw somebody else's number.
                $team->allocated = $this->cap($state['allocated']);
                $team->auction_purse = $this->cap($state['auction_purse']);
                $team->retained_spent = $state['retained_spent'];
                $team->auction_spent = $state['auction_spent'];
                $team->retained_count = $state['retained_count'];
                $team->retained_expected = $state['retained_expected'];
                $team->max_bid_allowed = $this->cap($state['max_bid_allowed']);
                $team->reserve_amount = $state['reserve'];
                $team->slots_required = $state['slots_required'];
                $team->slots_remaining = $state['slots_remaining'];
                $team->excluded = $state['excluded'];
                $team->squad_full = $state['squad_full'];
                $team->squad_size = $state['squad_size'];
                // Two different exclusions, and the difference matters to the person reading it:
                // one is "cannot afford this player", the other is "has no place left for any".
                $team->exclusion_reason = match (true) {
                    $state['squad_full'] => sprintf(
                        'Squad is full — %d of %d places taken.',
                        $state['slots_filled'],
                        $state['squad_size']
                    ),
                    (bool) $state['excluded'] => sprintf(
                        'Can only bid up to %s — must retain %s for %d more squad slot%s.',
                        format_points($state['max_bid_allowed']),
                        format_points($state['reserve']),
                        max(0, $state['slots_remaining'] - 1),
                        max(0, $state['slots_remaining'] - 1) === 1 ? '' : 's'
                    ),
                    default => null,
                };

                return $team;
            });
    }

    /**
     * Open tournaments have no budget cap and report PHP_FLOAT_MAX, which does not
     * survive JSON encoding. Clamp to a large finite number for the client.
     */
    private function cap(float $value): float
    {
        return $value >= 1.0e15 ? 1.0e15 : $value;
    }

    /**
     * Display the Organizer's control panel view.
     */
    public function showPanel(Auction $auction)
    {
        $auction->load('tournament');

        // Fetch available players (waiting status)
        // Locked to the active pool when one is running, so the queue can never offer
        // a player the server would refuse to put on the block.
        $availablePlayers = $this->pools->waitingPlayersQuery($auction)
            ->inLotOrder()
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        // Fetch current player on auction (if any)
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                // Constrained to live bids: the log is append-only, so a retracted
                // (undone) bid is still present and must not appear as a standing bid.
                'bids' => fn ($q) => $q->where('is_void', false)->with(['team', 'user']),
                'soldToTeam',
            ])
            ->first();

        // Fetch sold players
        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->get();

        // Fetch teams with their budget calculations
        $teams = $this->teamsWithPurse($auction, $currentPlayer);

        // Stats
        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $auction->auctionPlayers()->where('status', 'sold')->count(),
            'unsold_count' => $auction->auctionPlayers()->where('status', 'unsold')->count(),
            'skipped_count' => $auction->auctionPlayers()->where('status', 'skipped')->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        /*
         * The clock as it stands right now, so the panel does not have to guess it at load.
         *
         * Without this the panel started a FULL countdown on every page load and only learned
         * the truth from the first poll two seconds later — so refreshing a screen whose timer
         * had already run out showed a healthy clock ticking down from the top, then snapped to
         * expired. Reloading is exactly what an operator does when something looks wrong, and
         * it was the one moment the panel lied about the state of the room.
         */
        $timerState = $auction->timerStateFor($currentPlayer);

        /*
         * Pool state in the FIRST paint, not one poll later.
         *
         * The panel's Alpine state started with `activePool: null` and `pools: []`, and the pool
         * strip reads that as "no pool running — no enabled pool has players left". On every
         * reload the operator was told the auction had no pool, in amber, for as long as the
         * first poll took. Seeded here so the very first frame is correct rather than merely
         * quiet.
         */
        return view('backend.pages.auction.organizer-panel', compact(
            'auction',
            'availablePlayers',
            'currentPlayer',
            'soldPlayers',
            'teams',
            'stats',
            'timerState'
        ) + [
            'canControl' => $this->canControl($auction),
            'canSell' => $this->canSell($auction),
            'canScreens' => $this->canScreens($auction),
            'canPools' => $this->canPools($auction),
            'poolProgress' => $this->pools->poolProgress($auction),
        ]);
    }

    /**
     * Display the fullscreen offline auction control panel.
     */
    public function showOfflinePanel(Auction $auction)
    {
        $auction->load('tournament');

        // Locked to the active pool when one is running, so the queue can never offer
        // a player the server would refuse to put on the block.
        $availablePlayers = $this->pools->waitingPlayersQuery($auction)
            ->inLotOrder()
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                // Constrained to live bids: the log is append-only, so a retracted
                // (undone) bid is still present and must not appear as a standing bid.
                'bids' => fn ($q) => $q->where('is_void', false)->with(['team', 'user']),
                'soldToTeam',
                'currentBidTeam',
            ])
            ->first();

        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->get();

        $unsoldPlayers = $auction->auctionPlayers()
            ->where('status', 'unsold')
            ->with(['player'])
            ->get();

        $teams = $this->teamsWithPurse($auction, $currentPlayer);

        $skippedPlayers = $auction->auctionPlayers()
            ->where('status', 'skipped')
            ->with(['player'])
            ->get();

        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $soldPlayers->count(),
            'unsold_count' => $unsoldPlayers->count(),
            'skipped_count' => $skippedPlayers->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        $bidRules = is_string($auction->bid_rules) ? json_decode($auction->bid_rules, true) : ($auction->bid_rules ?? []);

        // Map available players to compact format for JSON in Blade
        $availablePlayersCompact = $availablePlayers->map(function ($ap) {
            return [
                'id' => $ap->id,
                'player_id' => $ap->player_id,
                // Needed by the panel's next-player rule — see projectAvailablePlayers().
                'auction_pool_id' => $ap->auction_pool_id,
                'base_price' => $ap->base_price,
                'jersey_number' => $ap->player->jersey_number ?? null,
                'player' => $ap->player,
            ];
        });

        return view('backend.pages.auction.offline-panel', compact(
            'auction',
            'availablePlayers',
            'availablePlayersCompact',
            'currentPlayer',
            'soldPlayers',
            'unsoldPlayers',
            'teams',
            'stats',
            'bidRules'
        ));
    }

    /**
     * May this user CHANGE the auction, or only watch it run?
     *
     * The panel is reachable with `auction.observe` alone, which is what an Auctioneer holds:
     * they call the lots in the room and need the board, the queue and every team's purse in
     * front of them, but nothing that sells, passes, skips or undoes. The routes enforce this
     * — every POST in the organizer group requires `auction.control` — and this flag is only
     * so the panel does not offer buttons that would come back 403. The guard is the route;
     * this is the courtesy.
     */
    /**
     * May this seat change the auction at all — take bids, run the clock, move the lot on?
     *
     * The permission is only half the question. An operator named on this auction with `observe`
     * and nothing else holds `auction.control` through the Auctioneer role — that is what gets
     * them to the panel — so asking the permission alone showed them NEXT, PASS, UNDO and SELL,
     * every one of which the routes then refused. A control panel with live buttons that 403 is
     * worse than a read-only one: in a hall, the operator presses it and waits.
     */
    private function canControl(?Auction $auction = null): bool
    {
        return $this->allows('auction.control', AuctionOperator::ABILITY_CONTROL, $auction);
    }

    /**
     * May this seat END a lot — sell, pass, skip, undo, end or restart the auction?
     *
     * Deliberately separate from control: the person calling the lots usually should not also be
     * the one ending them, which is the whole reason `sell` is its own ability.
     */
    private function canSell(?Auction $auction = null): bool
    {
        return $this->allows('auction.control', AuctionOperator::ABILITY_SELL, $auction);
    }

    /** May this seat change what the wall and the ticker are showing? */
    private function canScreens(?Auction $auction = null): bool
    {
        return $this->allows('auction.control', AuctionOperator::ABILITY_SCREENS, $auction);
    }

    /** May this seat start, close and reopen pools? */
    private function canPools(?Auction $auction = null): bool
    {
        return $this->allows('auction.control', AuctionOperator::ABILITY_POOLS, $auction);
    }

    /**
     * The permission AND, for somebody scoped to particular auctions, the ability on THIS one.
     *
     * Mirrors EnsureAuctionOperator exactly, because a button that appears and a route that
     * refuses are the same bug seen from two sides. Anyone not named on an auction is judged by
     * their permissions alone, as they always were.
     */
    private function allows(string $permission, string $ability, ?Auction $auction): bool
    {
        $user = auth()->user();

        if (! $user?->can($permission)) {
            return false;
        }

        if (! $auction || $user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return true;
        }

        $named = AuctionOperator::where('user_id', $user->id)->exists();

        if (! $named && ! $user->hasRole('Auctioneer')) {
            return true;
        }

        $operator = AuctionOperator::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->first();

        return $operator?->can($ability) ?? false;
    }

    /**
     * Download everything this auction currently knows, as a spreadsheet.
     *
     * A rescue hatch: when something goes wrong in the hall, the organizer needs the
     * state out of the system and onto a laptop straight away — who has been sold to
     * whom for how much, what each team has spent and has left, and who is still waiting
     * — without anyone having to open a database.
     *
     * Read-only by construction, so it is always safe to press. It streams to a temp file
     * rather than building the zip in memory: ZipArchive writes to a path, and a half
     * written file must never reach the browser as a download that opens as corrupt.
     */
    public function exportSnapshot(Auction $auction, AuctionSnapshotExport $export)
    {
        $path = tempnam(sys_get_temp_dir(), 'auction-export-');

        $export->build($auction)->save($path);

        // deleteFileAfterSend: the temp file is this request's alone, and without it the
        // system temp directory accumulates one workbook per press.
        return response()
            ->download($path, $export->filename($auction), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Return full auction state as JSON for polling.
     */
    public function pollState(Auction $auction)
    {
        // Locked to the active pool when one is running, so the queue can never offer
        // a player the server would refuse to put on the block.
        $availablePlayers = $this->pools->waitingPlayersQuery($auction)
            ->inLotOrder()
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                // Constrained to live bids: the log is append-only, so a retracted
                // (undone) bid is still present and must not appear as a standing bid.
                'bids' => fn ($q) => $q->where('is_void', false)->with(['team', 'user']),
                'soldToTeam',
            ])
            ->first();

        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $teams = $this->teamsWithPurse($auction, $currentPlayer);

        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $soldPlayers->count(),
            'unsold_count' => $auction->auctionPlayers()->where('status', 'unsold')->count(),
            'skipped_count' => $auction->auctionPlayers()->where('status', 'skipped')->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        $freshAuction = $auction->fresh();

        // Increment ladder resolved server-side so no client recomputes it.
        $bidState = $currentPlayer
            ? $this->increments->stateForPlayer($freshAuction, $currentPlayer)
            : null;

        $nextUndo = $this->undo->nextUndoable($freshAuction);
        $poolProgress = $this->pools->poolProgress($freshAuction);
        // The clock limit depends on whether the live player already has a bid.
        $timerState = $freshAuction->timerStateFor($currentPlayer);

        return response()->json([
            'auction_status' => $freshAuction->status,
            // Same two fields the public wall reads, from the same server-computed window,
            // so the panel and the hall screen announce a restart together instead of the
            // panel guessing from its own local state.
            'restarting' => $freshAuction->isRestarting(),
            'restart_seconds' => $freshAuction->restartNoticeRemaining(),
            'available_players' => $this->projectAvailablePlayers($availablePlayers),
            // Trimmed for the same reason as the queue above — see projectBids().
            'current_player' => $this->withTrimmedBids($currentPlayer),
            'sold_players' => $soldPlayers,
            'teams' => $teams,
            'stats' => $stats,
            'open_bid_mode' => $freshAuction->open_bid_mode,
            'mode_manually_overridden' => (bool) $freshAuction->mode_manually_overridden,
            'online_bid_limit_from' => $freshAuction->online_bid_limit_from,
            'online_bid_limit_to' => $freshAuction->online_bid_limit_to,
            'bid_type' => $freshAuction->bid_type,
            'closed_bid_starts_at' => $freshAuction->closed_bid_starts_at,
            /*
             * The price has reached the sealed threshold and nobody has said what to do
             * about it. The room no longer flips itself: the organizer is asked, and the
             * alternative to a sealed round is simply selling to the team already leading,
             * so the panel is handed who that is and for how much.
             */
            'sealed_threshold_pending' => $freshAuction->sealedThresholdPendingFor($currentPlayer),
            'sealed_threshold_leader' => $currentPlayer?->currentBidTeam?->name,
            'sealed_threshold_amount' => $currentPlayer ? (float) $currentPlayer->current_price : null,
            // Squad-reserve rule, so the panel can show why a team is locked out.
            'min_squad_size' => $freshAuction->minSquadSize(),
            'max_squad_size' => $freshAuction->maxSquadSize(),
            'min_price_per_player' => $freshAuction->minPricePerPlayer(),
            'bid_increment' => $bidState['increment'] ?? null,
            'next_bid_amount' => $bidState['next_bid_amount'] ?? null,
            'max_bid_reached' => $bidState['max_reached'] ?? false,
            /*
             * Where open bidding stops, when a sealed threshold still applies to this player.
             *
             * Sent so the panel can refuse a click at the ceiling on the spot. Without it the
             * chip posted, waited for the round trip and came back refused — which from the
             * operator's chair is a chip that does nothing.
             */
            'open_bid_ceiling' => $bidState['open_bid_ceiling'] ?? null,
            // Undo stack state for the panel's UNDO button.
            'can_undo' => $nextUndo !== null,
            'next_undo' => $nextUndo?->description,
            /*
             * What that undo will ALSO do, worked out from the state as it is now.
             *
             * The log's own description is written when the action happens and cannot know
             * later consequences, so the confirm dialog offered "Will undo: Bid 8.1M by TEST
             * Delta" over a live sealed board the same click was about to cancel.
             */
            'next_undo_notes' => $this->undo->previewFor($freshAuction)['notes'],
            // Pool lock: which pool is running and how far through it we are.
            'active_pool' => $poolProgress['active_pool'],
            'next_pool' => $poolProgress['next_pool'],
            'pools' => $poolProgress['pools'],
            /*
             * The unsold pile, separately from `pools`.
             *
             * `pools` is biddable()-scoped and is what "the next pool" and every pool listing
             * read, so the pile must not be in it — nothing should pick it up automatically.
             * The organizer can still choose to run it deliberately, and this is what lets the
             * panel offer that.
             */
            'unsold_pool' => $poolProgress['unsold_pool'] ?? null,
            // Timer, driven off the server clock.
            'timer_enabled' => $timerState['applies'],
            'timer_expiry_action' => $freshAuction->timer_expiry_action,
            'bid_timer_seconds' => $timerState['limit'],
            'timer_seconds_remaining' => $timerState['remaining'],
            'timer_expired' => $timerState['expired'],
            // One flag, shared by the panel, the wall and the ticker.
            'timer_paused' => $timerState['paused'],
            // Closing calls: the stage the clock has reached, plus the thresholds so
            // the panel can escalate between polls without waiting for the next one.
            'final_call' => $timerState['final_call'],
            'final_call_stages' => $timerState['final_call_stages'],
            'server_time' => now()->timestamp,
            'quick_bid_steps' => $freshAuction->quickBidSteps(),
            // What amounts are called, so a mid-auction change reaches every screen.
            'amount_unit' => $freshAuction->amountUnitConfig(),
        ]);
    }

    /**
     * Start the auction.
     */
    public function startAuction(Auction $auction)
    {
        // Guarded: without this a completed auction could be "started" again,
        // silently reopening bidding on a finished event.
        if (! in_array($auction->status, ['scheduled', 'paused'], true)) {
            return response()->json([
                'success' => false,
                'message' => $auction->status === 'running'
                    ? 'This auction is already running.'
                    : 'A completed auction cannot be started. Use Restart to reset it.',
            ], 422);
        }

        $auction->update(['status' => 'running']);

        /*
         * Release any clock left frozen by an earlier pause.
         *
         * This set `status` and nothing else, so Pause followed later by Start produced an
         * auction that was RUNNING with `timer_paused_at` still set — and the two screens
         * read different fields. The wall's paused overlay keys off `status`, so it showed
         * nothing; the countdown keys off `timer_paused`, so it sat frozen at a fixed number
         * with no explanation anywhere. It stayed that way for hours on the live auction.
         *
         * stopTimer() rather than resumeTimer(): nobody is on the block at the moment an
         * auction starts, so the clock should be clear, not shifted forward by however long
         * the pause lasted.
         */
        $auction->stopTimer();

        broadcast(new AuctionStatusUpdate($auction->id, 'running'));

        // In-app notices only, and only when this auction wants them.
        if ($this->mail->notificationsEnabled($auction) && ! $this->mail->isTestMode($auction)) {
            $auctionPlayers = $auction->auctionPlayers()->with('player.user')->get();
            foreach ($auctionPlayers as $ap) {
                if ($ap->player?->user) {
                    $ap->player->user->notify(new GeneralNotification(
                        "Auction '{$auction->name}' has started!",
                        route('admin.auctions.show', $auction),
                        'info'
                    ));
                }
            }
        }

        return response()->json(['message' => 'Auction has been started.']);
    }

    /**
     * End the auction.
     */
    public function endAuction(Auction $auction)
    {
        $auction->update(['status' => 'completed']);
        broadcast(new AuctionStatusUpdate($auction->id, 'completed'));

        // In-app notices only — cheap, and meaningless if deferred.
        if ($this->mail->notificationsEnabled($auction) && ! $this->mail->isTestMode($auction)) {
            $auctionPlayers = $auction->auctionPlayers()->with('player.user')->get();
            foreach ($auctionPlayers as $ap) {
                if ($ap->player?->user) {
                    $ap->player->user->notify(new GeneralNotification(
                        "Auction '{$auction->name}' has ended.",
                        route('admin.auctions.show', $auction),
                        'info'
                    ));
                }
            }
        }

        // The auction is over, so everything held back now goes out — queued, so this
        // request returns immediately rather than waiting on a few hundred emails.
        $outbox = AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count();
        if ($outbox > 0) {
            FlushAuctionEmails::dispatch($auction);
        }

        return response()->json([
            'message' => 'Auction has been completed.',
            'queued_emails' => $outbox,
        ]);
    }

    /**
     * Restart an auction — resets all players and bids back to initial state.
     */
    public function restartAuction(Auction $auction)
    {
        if (! in_array($auction->status, ['completed', 'running', 'paused'])) {
            return response()->json(['message' => 'Auction cannot be restarted from this state.'], 422);
        }

        DB::transaction(function () use ($auction) {
            /*
             * Every player, through the same unwinding the pool restart uses — a sold player
             * left holding their roster row, team pivot or Player role is the bug that
             * shared helper exists to prevent.
             */
            $this->unwindAuctionPlayers($auction, $auction->auctionPlayers()->get());

            // Belt and braces on top of the per-player deletes: a whole-auction restart
            // means nothing from the previous run survives, including any log row that
            // was never tied to a player.
            AuctionBid::where('auction_id', $auction->id)->delete();
            AuctionActionLog::where('auction_id', $auction->id)->delete();

            /*
             * And every POOL back to pending, which this did not do.
             *
             * The players were all unwound to `waiting` above, but the pools they sit in kept
             * whatever status they had — so a pool completed before the restart stayed
             * `completed` while holding a full queue of players nobody had bid on. Measured on
             * live: auction 11's Pool B read `completed` with 15 waiting players and no sales.
             *
             * That is not only a wrong label. `activatePool()` refuses a completed pool, so the
             * organizer could not choose it — a restart that resets every player but leaves half
             * the pools unrunnable.
             *
             * PENDING, not ACTIVE: a restart puts the whole auction back to the start line and
             * the organizer picks which pool goes first. Same three fields `reopenPool()` resets
             * for a single pool, so the two agree about what "runnable again" means.
             */
            AuctionPool::where('auction_id', $auction->id)->update([
                'status' => AuctionPool::STATUS_PENDING,
                'completed_at' => null,
                'activated_at' => null,
            ]);

            $auction->update([
                'status' => 'running',
                // Without this the big screen keeps counting down a player who is no
                // longer on the block — a live clock over the waiting screen.
                'timer_started_at' => null,
                // And the pause mark with it. Restarting while paused used to leave this
                // set, so the auction came back RUNNING with a clock frozen before it
                // started — the same split state as Start, reached a different way.
                'timer_paused_at' => null,
                // Announce the restart instead of silently blanking to the waiting state.
                'restarted_at' => now(),
            ]);
        });

        broadcast(new AuctionStatusUpdate($auction->id, 'running'));
        return response()->json(['success' => true, 'message' => 'Auction restarted. All players reset.']);
    }

    /**
     * Restart the running pool, and only it.
     *
     * The whole-auction restart is the wrong tool once an auction is several pools deep:
     * re-running one pool meant wiping every pool, so a completed pool could not be redone
     * without throwing away the ones after it.
     *
     * Every player in the pool goes back to waiting, sales included — those are undone and
     * the teams get their purse back, which is the point of re-auctioning a pool rather
     * than taking a second pass at whoever went unsold. Players outside the pool, their
     * bids and their undo history are untouched.
     */
    /**
     * Put the board of sold players up on the public screens, or take it down.
     *
     * Between lots there is a natural gap — the room wants to see where the money has gone, and
     * until now the only answer was the operator's own screen. The board is the same sold feed
     * the wall already publishes, laid out as cards.
     *
     * Stored AND pushed. Stored, so a wall plugged in or reloaded while the board is up comes
     * back to the board rather than to a live card the room is not looking at; pushed, so the
     * screens change when the button is pressed rather than up to two seconds later, which on a
     * wall reads as hesitation.
     */
    public function toggleSoldBoard(Request $request, Auction $auction): JsonResponse
    {
        /*
         * Guarded on the route, like every other action on this panel.
         *
         * This asked for `auction.edit` here instead — the permission that opens the configuration
         * wizard — which is a different and much bigger thing than putting a board on a wall. It
         * was the only action in this controller authorizing itself, and the effect was that the
         * `screens` ability could never work: an operator given the projector still failed on a
         * permission they have no business holding. The route now carries
         * `auction.control|auction.edit` plus `auction.operator:screens`, which is the pattern
         * every sibling here uses.
         */

        $data = $request->validate([
            // Null takes the screens back to the live card.
            'board' => 'nullable|in:' . implode(',', Auction::publicBoards()),
            // How long the break is meant to last. Null or 0 puts a board up with no clock,
            // which is right for a sealed round or a board shown mid-lot.
            'break_minutes' => 'nullable|integer|min:0|max:180',
            // Which screens it plays on. The wall is the room and the ticker is the stream, and
            // filling a break in the hall is not the same decision as cutting away the broadcast.
            'target' => 'nullable|in:' . implode(',', Auction::boardTargets()),
            // Saved with the rest of the decision, so "no ads tonight" survives a reload rather
            // than being a switch somebody has to remember to flick again.
            'ads_slides' => 'nullable|boolean',
            'ads_sponsors' => 'nullable|boolean',
        ]);

        /*
         * Stored exactly as asked. The caller decides; this does not second-guess it.
         *
         * There used to be a toggle here — "the same board again means take it down" — and the
         * dialog that replaced the buttons ALSO has that rule, so the two applied it in turn.
         * Pressing Apply a second time without changing the board sent the same value, the
         * server saw it matching what was already stored, and quietly turned the board OFF. From
         * the operator's chair the dialog stopped working on the second save.
         *
         * Toggling belongs where the press happens, because only the caller knows whether the
         * operator picked a value or pressed a button twice.
         */
        $board = $data['board'] ?? null;

        $minutes = (int) ($data['break_minutes'] ?? 0);

        $auction->update([
            'public_board' => $board,
            /*
             * The deadline goes up with the board and comes down with it.
             *
             * Stamped as an instant rather than a duration so every screen counts down to the
             * same moment; cleared when the board comes down, because a countdown left running
             * behind the live card would resurface on the next board with a time nobody set.
             */
            'break_ends_at' => ($board !== null && $minutes > 0) ? now()->addMinutes($minutes) : null,
            'public_board_target' => $data['target'] ?? $auction->public_board_target ?? 'both',
            'ads_slides_enabled' => array_key_exists('ads_slides', $data)
                ? (bool) $data['ads_slides'] : $auction->ads_slides_enabled,
            'ads_sponsors_enabled' => array_key_exists('ads_sponsors', $data)
                ? (bool) $data['ads_sponsors'] : $auction->ads_sponsors_enabled,
        ]);

        $fresh = $auction->fresh();

        \App\Support\AfterResponse::run(
            fn () => \App\Events\SoldBoardToggled::announce(
                (int) $auction->id,
                $board,
                $fresh->public_board_target ?? 'both',
                (bool) $fresh->ads_slides_enabled,
                (bool) $fresh->ads_sponsors_enabled
            )
        );

        return response()->json([
            'success' => true,
            'board' => $board,
            'break_remaining' => $auction->fresh()->breakRemaining(),
            'target' => $auction->fresh()->public_board_target,
            'ads_slides' => (bool) $auction->fresh()->ads_slides_enabled,
            'ads_sponsors' => (bool) $auction->fresh()->ads_sponsors_enabled,
            'message' => match ($board) {
                Auction::BOARD_SOLD => 'Sold board is on the screens.',
                Auction::BOARD_HIGHLIGHTS => 'Highlights are on the screens.',
                default => 'Back to the live card.',
            },
        ]);
    }

    public function restartPool(Request $request, Auction $auction, AuctionPool $pool)
    {
        if ((int) $pool->auction_id !== (int) $auction->id) {
            return response()->json(['success' => false, 'message' => 'That pool belongs to a different auction.'], 422);
        }

        if (! in_array($auction->status, ['completed', 'running', 'paused'], true)) {
            return response()->json(['success' => false, 'message' => 'This auction cannot be restarted from its current state.'], 422);
        }

        /*
         * A player mid-bid belongs to the run being wiped, so finishing them first is the
         * ordinary path — it keeps the reset from stranding a live board.
         *
         * But it cannot be the ONLY path. A player whose clock has run out, or who was left
         * on the block by an undo, cannot be finished: Sell needs a bid, Pass refuses a player
         * who has one, and the timer will not expire twice. That left the pool unrestartable
         * with no way forward at all, in the middle of an auction. `force` is that way
         * forward: the live player is reset with the rest of the pool, which is what a restart
         * means anyway.
         *
         * Still a separate, deliberate act rather than the default, because the guard is right
         * in every case except the stuck one — and the caller has to say so.
         */
        $force = $request->boolean('force');
        $onBlock = $auction->auctionPlayers()->where('status', 'on_auction')->exists();

        if ($onBlock && ! $force) {
            return response()->json([
                'success' => false,
                'message' => 'Finish the player currently on the block before restarting the pool.',
                // The panel keys off this to offer a forced restart rather than guessing from
                // the wording of the message.
                'player_on_block' => true,
            ], 422);
        }

        /*
         * WHICH players come back, chosen by the organizer.
         *
         * This used to reset every non-retained player in the pool, so wanting the unsold
         * players back on the block cost you every sale in that pool as well. The panel now
         * asks, and defaults to all three — an absent `include` means the old behaviour, which
         * keeps any other caller working.
         *
         * `on_auction` is always in the set: whoever is on the block belongs to the run being
         * wiped, and a forced restart that left them there would strand a live board. That is
         * what `force` is agreeing to, so it is not a separate choice.
         */
        $allStatuses = ['sold', 'unsold', 'skipped'];
        $include = $request->input('include');
        $include = is_array($include) && $include !== []
            ? array_values(array_intersect($allStatuses, $include))
            : $allStatuses;

        if ($include === []) {
            return response()->json([
                'success' => false,
                'message' => 'Choose at least one kind of player to put back on the block.',
            ], 422);
        }

        // Retained players were never auctioned, so they are not part of a re-run.
        $auctionPlayers = $auction->auctionPlayers()
            ->where('auction_pool_id', $pool->id)
            ->where('is_retained', false)
            ->whereIn('status', array_merge($include, ['on_auction']))
            ->get();

        if ($auctionPlayers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => sprintf('%s has no %s players to restart.', $pool->name, implode(' or ', $include)),
            ], 422);
        }

        DB::transaction(function () use ($auction, $pool, $auctionPlayers) {
            $this->unwindAuctionPlayers($auction, $auctionPlayers);

            // Back in play, so it serves its players again. activatePool() refuses while
            // another pool is running, which is the correct guard but not reachable here:
            // this pool is the one the auction is already locked to.
            $pool->update(['status' => AuctionPool::STATUS_ACTIVE]);

            $auction->update([
                'status' => 'running',
                'timer_started_at' => null,
                'timer_paused_at' => null,
                'restarted_at' => now(),
            ]);
        });

        broadcast(new AuctionStatusUpdate($auction->id, 'running'));

        return response()->json([
            'success' => true,
            // Names what actually happened rather than "restarted": with a partial restart the
            // count alone does not tell the organizer whether the sales were unwound.
            'message' => sprintf(
                '%s restarted — %d %s player%s back on the block.',
                $pool->name,
                $auctionPlayers->count(),
                implode(' / ', $include),
                $auctionPlayers->count() === 1 ? '' : 's'
            ),
            'progress' => $this->pools->poolProgress($auction->fresh()),
        ]);
    }

    /**
     * Put a set of auction players back to waiting, undoing anything a sale attached.
     *
     * Shared with the whole-auction restart so the two cannot drift on what "reset" means:
     * a sold player who keeps their roster row, team pivot or Player role is the bug this
     * exists to prevent, and it is easy to remember one of the four and forget another.
     *
     * @param  \Illuminate\Support\Collection<int, AuctionPlayer>  $auctionPlayers
     */
    private function unwindAuctionPlayers(Auction $auction, $auctionPlayers): void
    {
        $auctionPlayerIds = $auctionPlayers->pluck('id');
        $soldPlayerIds = $auctionPlayers->where('status', 'sold')->pluck('player_id');

        if ($soldPlayerIds->isNotEmpty()) {
            Player::whereIn('id', $soldPlayerIds)->update([
                'player_mode' => 'normal',
                'actual_team_id' => null,
            ]);

            if ($auction->tournament_id) {
                DB::table('player_actual_team_tournament')
                    ->whereIn('player_id', $soldPlayerIds)
                    ->where('tournament_id', $auction->tournament_id)
                    ->delete();
            }

            $soldUserIds = Player::whereIn('id', $soldPlayerIds)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            if ($soldUserIds->isNotEmpty()) {
                $teamIds = ActualTeam::forTournament($auction->tournament_id)->pluck('id');
                DB::table('actual_team_users')
                    ->whereIn('user_id', $soldUserIds)
                    ->whereIn('actual_team_id', $teamIds)
                    ->where('role', 'Player')
                    ->delete();
            }
        }

        AuctionPlayer::whereIn('id', $auctionPlayerIds)->update([
            'status' => 'waiting',
            'current_price' => DB::raw('base_price'),
            'current_bid_team_id' => null,
            'sold_to_team_id' => null,
            'final_price' => null,
        ]);

        /*
         * Bids and undo history for these players only.
         *
         * Team purses are derived from live bids and sold rows rather than stored, so
         * clearing the bids is what gives the money back — there is no budget column to
         * put right. The undo entries go with them because they describe a run that no
         * longer exists, and undoing into it would restore state from a wiped auction.
         */
        AuctionBid::where('auction_id', $auction->id)
            ->whereIn('auction_player_id', $auctionPlayerIds)
            ->delete();

        AuctionActionLog::where('auction_id', $auction->id)
            ->whereIn('auction_player_id', $auctionPlayerIds)
            ->delete();

        // Sealed rounds are abandoned rather than deleted: the record of a disputed round
        // outlives the run it belonged to.
        $closedBids = app(\App\Services\Auction\ClosedBidService::class);
        AuctionPlayer::whereIn('id', $auctionPlayerIds)
            ->whereNotNull('closed_bid_round_id')
            ->get()
            ->each(fn ($ap) => $closedBids->abandonRoundsFor($ap));
    }

    /**
     * Select the next player and put them up for bidding.
     */
    // public function putPlayerOnBid(Request $request, Auction $auction)
    // {
    //     $validated = $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

    //     $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
    //         ->where('auction_id', $auction->id)
    //         ->firstOrFail();

    //     // Reset any other 'on_auction' players
    //     $auction->auctionPlayers()->where('status', 'on_auction')->update(['status' => 'waiting']);

    //     $auctionPlayer->update([
    //         'status' => 'on_auction',
    //         'current_price' => $auctionPlayer->base_price,
    //         'current_bid_team_id' => null,
    //     ]);

    //     // **THE FIX**: Eager-load the relationships the frontend needs BEFORE broadcasting.
    //     // We use fresh() to get the latest state after our update.
    //     $playerDataForBroadcast = $auctionPlayer->fresh([
    //         'player.playerType',
    //         'player.battingProfile',
    //         'player.bowlingProfile',
    //         'bids.team', // Load all bids and their associated team
    //         'bids.user' // Also load the user who placed the bid
    //     ]);

    //     broadcast(new PlayerOnBid($playerDataForBroadcast));

    //     return response()->json(['message' => 'Player is now live for bidding.']);
    // }

    public function putPlayerOnBid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->where('status', 'waiting') // ✅ only select waiting players
            ->first();

        // If no player found (either doesn't exist or not waiting)
        if (! $auctionPlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Player not available to put on bid. Only players with status "waiting" can be selected.',
            ], 400);
        }

        // Check if any other player is live
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Some player is already live in the auction! Please close that bid before starting with the next player!',
            ], 400);
        }

        // Pool lock: while a pool is running, only its players may be auctioned.
        $activePool = $this->pools->activePool($auction);
        if ($activePool && (int) $auctionPlayer->auction_pool_id !== (int) $activePool->id) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    '%s is running. Finish or close it before auctioning a player from another pool.',
                    $activePool->name
                ),
            ], 400);
        }

        /*
         * Set this player live, never below the auction's own opening figure.
         *
         * The base price is stored per player, and rows written before the floor existed still
         * carry whatever they were given — 1.00, from the days when an untouched pool's default
         * beat the auction's setting. Those rows survive in places a one-off repair does not
         * reach: an unsold player going back on the block, a pool reopened, an auction restarted.
         *
         * So the floor is applied HERE, at the moment a lot opens, as well as when the row is
         * written. That is the last point before a hall sees a figure, and it means no legacy row
         * can put a player up at one point however it got there. The row is corrected too, so the
         * queue and the poster agree with the board.
         */
        $floor = (float) ($auction->base_price ?? 0);
        $openingPrice = max((float) $auctionPlayer->base_price, $floor);

        $auctionPlayer->update([
            'status' => 'on_auction',
            'base_price' => $openingPrice,
            'starting_price' => $openingPrice,
            'current_price' => $openingPrice,
            'current_bid_team_id' => null,
        ]);

        // Reset phase for new player. A new player starts at their base price, so the sealed
        // phase resets with them.
        $phaseReset = [];

        if ($auction->hasAutoPhaseTransition()) {
            $phaseReset['bid_type'] = 'open';
            $phaseReset['bid_type_manually_overridden'] = false;
        }

        /*
         * Every lot opens OFFLINE — the organizer calling the room.
         *
         * This did the opposite: it flipped an offline auction back to online for each new
         * player unless the mode had been set by hand, on the reasoning that a price rule which
         * turned offline on for one lot should not silently hold for the next.
         *
         * The room works the other way round. Bidding is called out loud and the organizer
         * records it; online is the exception, switched on deliberately for a lot that wants it.
         * Starting each player online meant the first raises of every single lot went to a mode
         * nobody was using, and the operator had to keep pressing Offline.
         *
         * The manual flag is cleared with it, because this now happens on every lot whatever was
         * chosen for the last one — leaving the flag set would claim a choice that is no longer
         * being honoured.
         */
        $phaseReset['open_bid_mode'] = 'offline';
        $phaseReset['mode_manually_overridden'] = false;

        if ($phaseReset !== []) {
            $auction->update($phaseReset);
        }

        // Start the clock. Server-stamped so a slow or tampered browser cannot extend
        // the round. Also clears any pause left over from the previous player.
        $auction->startTimer();

        // Eager-load relationships for broadcast
        $playerDataForBroadcast = $auctionPlayer->fresh([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'bids.team',
            'bids.user',
        ]);

        broadcast(new PlayerOnBid($playerDataForBroadcast));

        return response()->json([
            'success' => true,
            'message' => 'Player is now live for bidding.',
        ]);
    }

    /**
     * Mark the current player as "Sold" to the highest bidder.
     */
    public function sellPlayer(Request $request, Auction $auction)
    {
        /*
         * The hammer cannot fall while the room is on hold.
         *
         * Bidding is already refused during a pause, so selling through it awarded a player
         * at a price no one was allowed to answer — the teams are told to stop and the one
         * irreversible action carried on regardless. Enforced here rather than only by
         * greying the button, because the S shortcut reaches this without touching it.
         */
        if ($auction->status === 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'The auction is paused — resume it before selling.',
            ], 422);
        }

        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        // Find the winning bid (highest standing amount — retracted bids don't win).
        $winningBid = $auctionPlayer->liveBids()->orderByDesc('amount')->first();

        if (! $winningBid) {
            // No bids: the player goes unsold.
            return $this->passPlayer($request, $auction);
        }

        $team = $winningBid->team;
        $amount = (float) $winningBid->amount;

        // Squad-reserve rule: the winning team must still be able to fill the rest
        // of its squad after this purchase.
        if (! $this->pools->canAffordWithReserve($auction, (int) $winningBid->team_id, $amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot sell. ' . $this->pools->reserveBlockedMessage(
                    $auction,
                    (int) $winningBid->team_id,
                    $amount,
                    $team?->name
                ),
            ], 400);
        }

        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot sell: the winning bid has no team attached.',
            ], 400);
        }

        // One shared sale path for open-bid SELL, sealed-bid award and allotment,
        // so every route writes the same stores and can be undone the same way.
        $snapshot = $this->sales->applySale($auctionPlayer, $team, $amount);

        $this->undo->record(
            $auction,
            AuctionActionLog::ACTION_SELL,
            $auctionPlayer,
            $snapshot + [
                'amount' => $amount,
                'team_id' => $team->id,
                'team_name' => $team->name,
            ],
            sprintf('Sold to %s for %s', $team->name, format_points($amount))
        );

        /*
         * One email per sale.
         *
         * This sent a SECOND one — applySale() above already raises the welcome card, which now
         * carries the sold poster, so a panel sale emailed the player twice while a draw or an
         * allotment emailed them once. Three routes, three outcomes, for what is the same event.
         *
         * `notifyPlayerSold()` and the TYPE_SOLD mailable stay: the outbox can still raise one by
         * hand and the preview renders it. What has gone is the automatic duplicate.
         */

        // Nobody is on the block now, so the clock stops. Left running it counted through
        // the gap to the next player and arrived already expired.
        $auction->stopTimer();

        return response()->json([
            'success' => true,
            'message' => 'Player sold to ' . $team->name . ' for ' . format_points($amount) . '.',
        ]);
    }

    /**
     * Mark the current player as "Unsold/Passed".
     */
    public function passPlayer(Request $request, Auction $auction)
    {
        /*
         * Nothing is settled while the room is on hold — passing a player is as final as
         * selling one, and the teams cannot bid to stop it. Same 422 as the sell paths.
         *
         * sellPlayer() falls through to here when there are no bids, but refuses on pause
         * before it gets this far, so that route never arrives in a paused auction.
         */
        if ($auction->status === 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'The auction is paused — resume it before passing a player.',
            ], 422);
        }

        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        // Scoped to this auction: without the auction_id filter this could mark a
        // player in a completely different auction as unsold.
        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        // Prevent passing a player who has active bids
        if ($auctionPlayer->current_bid_team_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot pass a player who has active bids. Use SELL instead.',
            ], 422);
        }

        $this->undo->record(
            $auction,
            AuctionActionLog::ACTION_PASS,
            $auctionPlayer,
            [
                'auction_player' => [
                    'status' => $auctionPlayer->status,
                    'current_price' => $auctionPlayer->current_price,
                    'current_bid_team_id' => $auctionPlayer->current_bid_team_id,
                    'final_price' => $auctionPlayer->final_price,
                    // The pool they came from, so Undo puts them back in it rather than
                    // leaving them stranded in the unsold holding pool.
                    'auction_pool_id' => $auctionPlayer->auction_pool_id,
                    'lot_number' => $auctionPlayer->lot_number,
                ],
            ],
            'Passed (unsold)'
        );

        $auctionPlayer->update(['status' => 'unsold']);

        // Nobody bid, so the player is set aside in their pool's unsold holding pool
        // for final allotment once the auction is done.
        $this->pools->moveToUnsoldPool($auctionPlayer);

        // Still broadcast the "sold" event so the UI can update, but without a winning team
        broadcast(new PlayerSoldEvent($auctionPlayer, null));

        // Held in the outbox with the rest of the auction's mail.
        $this->mail->raise($auction, AuctionPendingEmail::TYPE_UNSOLD, $auctionPlayer);

        // Nobody on the block, so the clock stops with them.
        $auction->stopTimer();

        return response()->json(['message' => 'Player has been passed.']);
    }

    /**
     * Who a team actually holds, and what each of them cost.
     *
     * Deliberately NOT folded into pollState(): that runs every two seconds for every open
     * panel, and a roster per team would multiply its cost by the squad size. This is fetched
     * once, when the organizer opens a team.
     *
     * `players.player_mode` cannot classify these — selling sets it to `retained`, so a buy
     * and a keep are indistinguishable by that column and the value `sold` is never written.
     * The auction rows are the only honest source.
     */
    public function teamSquad(Auction $auction, ActualTeam $team)
    {
        abort_unless((int) $team->tournament_id === (int) $auction->tournament_id, 404);

        $rows = AuctionPlayer::where('auction_id', $auction->id)
            ->where(function ($q) use ($team) {
                $q->where(fn ($sold) => $sold->where('status', 'sold')->where('sold_to_team_id', $team->id))
                    ->orWhere(fn ($kept) => $kept->where('is_retained', true)->where('team_id', $team->id));
            })
            ->with(['player:id,name,image_path,total_matches,total_runs,total_wickets,player_type_id', 'player.playerType'])
            ->get()
            ->map(function (AuctionPlayer $ap) use ($team) {
                $bought = $ap->status === 'sold' && (int) $ap->sold_to_team_id === (int) $team->id;
                $price = (float) ($bought ? $ap->final_price : $ap->retained_price);

                return [
                    'id' => $ap->id,
                    'name' => $ap->player->name ?? 'Player',
                    'role' => $ap->player?->playerType?->type,
                    'image' => $ap->player?->image_path ? asset('storage/' . $ap->player->image_path) : null,
                    'acquisition' => $bought ? 'auction' : 'retained',
                    'price' => $price,
                    // Self-declared career figures — the only ones that exist.
                    'matches' => $ap->player?->total_matches,
                    'runs' => $ap->player?->total_runs,
                    'wickets' => $ap->player?->total_wickets,
                ];
            })
            // Bought players first, then kept, dearest first inside each.
            ->sortBy([['acquisition', 'asc'], ['price', 'desc']])
            ->values();

        return response()->json([
            'team' => ['id' => $team->id, 'name' => $team->name],
            'players' => $rows,
            'totals' => [
                'auction' => $rows->where('acquisition', 'auction')->sum('price'),
                'retained' => $rows->where('acquisition', 'retained')->sum('price'),
                'count' => $rows->count(),
            ],
        ]);
    }

    public function togglePause(Auction $auction)
    {
        // 1. Determine the new status
        // If the current status is 'running', the new status will be 'paused'.
        // Otherwise, the new status will be 'running'.
        $newStatus = ($auction->status === 'running') ? 'paused' : 'running';

        // 2. Security/Logic Check: Only allow toggling if the auction is currently running or paused.
        if (! in_array($auction->status, ['running', 'paused'])) {
            return response()->json(['message' => 'Auction cannot be paused or resumed at this time.'], 422); // Unprocessable Entity
        }

        // 3. Update the auction's status in the database
        $auction->update(['status' => $newStatus]);

        /*
         * Freeze or release the bid clock with it.
         *
         * Pausing used to flip `status` and nothing else, while the countdown carried on as
         * wall-clock arithmetic — so a paused player came back with less time, or none.
         */
        if ($newStatus === 'paused') {
            $auction->pauseTimer();
        } else {
            $auction->resumeTimer();
        }

        // 4. Broadcast the status update to all connected clients
        // This is the crucial step that makes the UI update in real-time.
        broadcast(new AuctionStatusUpdate($auction->id, $newStatus));

        // 5. Return a success response to the Organizer's panel
        return response()->json(['message' => 'Auction status has been updated to ' . $newStatus . '.']);
    }

    /**
     * Sell a player to a specific team at a specific amount (closed bid mode).
     */
    public function sellToTeam(Request $request, Auction $auction)
    {
        // Paused means paused on this path too — it is the same hammer, reached from the
        // team picker rather than the SELL button.
        if ($auction->status === 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'The auction is paused — resume it before selling.',
            ], 422);
        }

        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'team_id' => 'required|exists:actual_teams,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        $team = ActualTeam::findOrFail($validated['team_id']);
        $amount = (float) $validated['amount'];

        // Squad-reserve rule, same as the open-bid SELL path.
        if (! $this->pools->canAffordWithReserve($auction, $team->id, $amount)) {
            return response()->json([
                'success' => false,
                'message' => $this->pools->reserveBlockedMessage($auction, $team->id, $amount, $team->name),
            ], 400);
        }

        // Audit bid for the sealed/offline award, so the amount appears in the bid
        // history and in every spend total derived from it.
        $auditBid = AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer->id,
            'player_id' => $auctionPlayer->player_id,
            'team_id' => $team->id,
            'user_id' => auth()->id(),
            'amount' => $amount,
            'bid_source' => 'offline',
        ]);

        // Same sale path as sellPlayer(). This previously wrote only
        // players.player_mode — no roster pivot, no actual_team_id, no welcome
        // card — so every sealed-bid and offline sale was missing from the team's
        // tournament roster.
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
            ],
            sprintf('Awarded to %s for %s', $team->name, format_points($amount))
        );

        // Send notifications
        /*
         * One email per sale.
         *
         * This sent a SECOND one — applySale() above already raises the welcome card, which now
         * carries the sold poster, so a panel sale emailed the player twice while a draw or an
         * allotment emailed them once. Three routes, three outcomes, for what is the same event.
         *
         * `notifyPlayerSold()` and the TYPE_SOLD mailable stay: the outbox can still raise one by
         * hand and the preview renders it. What has gone is the automatic duplicate.
         */

        // Nobody on the block, so the clock stops with them.
        $auction->stopTimer();

        return response()->json([
            'success' => true,
            'message' => 'Player sold to ' . $team->name . ' for ' . format_points($amount),
        ]);
    }

    /**
     * Close bidding for the current player (stop accepting bids).
     */
    public function closeBidding(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)
            ->where('auction_id', $auction->id)
            ->where('status', 'on_auction')
            ->firstOrFail();

        $auctionPlayer->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Bidding closed for this player.',
        ]);
    }

    /**
     * Switch bid type (open/closed) manually.
     */
    public function switchBidType(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'bid_type' => 'required|in:open,closed',
        ]);

        $auction->update([
            'bid_type' => $validated['bid_type'],
            'bid_type_manually_overridden' => true,
        ]);

        /*
         * Choosing CLOSED is now the deliberate way into a sealed round, so it has to
         * actually open one.
         *
         * It only ever set the flag. That left the auction in `closed` with no round for
         * the player on the block — a phase with nothing behind it, no board to run and no
         * way for a team to submit. The automatic path always created the round alongside
         * the flip; the manual path never did, and nobody noticed while the threshold was
         * doing the work.
         */
        $round = null;

        if ($validated['bid_type'] === 'closed') {
            $onBlock = $auction->auctionPlayers()->where('status', 'on_auction')->first();

            if ($onBlock) {
                $round = app(\App\Services\Auction\ClosedBidService::class)
                    ->openRoundFor($onBlock, $auction->fresh());
            }
        }

        return response()->json([
            'success' => true,
            'message' => $round
                ? 'Sealed round opened.'
                : 'Switched to ' . strtoupper($validated['bid_type']) . ' bid.',
            'bid_type' => $validated['bid_type'],
        ]);
    }

    /**
     * Switch between online and offline mode for open bid auctions.
     */
    public function switchMode(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'mode' => 'required|in:online,offline',
        ]);

        $newMode = $validated['mode'];

        // Determine if this is a manual override (admin switching to offline while price is in online range)
        $manualOverride = $auction->mode_manually_overridden;

        if ($newMode === 'offline') {
            // Admin is switching to offline — mark as manual override
            $manualOverride = true;
        } else {
            // Admin is switching back to online — clear the override flag
            $manualOverride = false;
        }

        $auction->update([
            'open_bid_mode' => $newMode,
            'mode_manually_overridden' => $manualOverride,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Switched to ' . strtoupper($newMode) . ' mode.',
            'open_bid_mode' => $newMode,
            'mode_manually_overridden' => $manualOverride,
        ]);
    }

    /**
     * Fetch sealed bids for a player on auction (closed bid mode).
     */
    public function fetchSealedBids(Request $request, Auction $auction)
    {
        $auctionPlayerId = $request->query('auction_player_id');

        $query = AuctionBid::where('auction_id', $auction->id)
            ->live()
            ->with(['team', 'user']);

        if ($auctionPlayerId) {
            $query->where('auction_player_id', $auctionPlayerId);
        }

        // The bid log is append-only, so a team can have several rows per player.
        // A sealed-bid board must show each team's standing (latest) bid only —
        // one line per team, ranked by amount.
        $bids = $query->orderByDesc('id')->get()
            ->unique(fn (AuctionBid $bid) => $bid->team_id . ':' . $bid->auction_player_id)
            ->sortByDesc(fn (AuctionBid $bid) => (float) $bid->amount)
            ->values()
            ->map(function ($bid) {
                return [
                    'id' => $bid->id,
                    'team_id' => $bid->team_id,
                    'team_name' => $bid->team->name ?? 'Unknown',
                    // `logo_path` does not exist on ActualTeam — the column is
                    // `team_logo`, so this always resolved to null.
                    'team_logo' => $bid->team?->team_logo_url,
                    'amount' => $bid->amount,
                    'user_name' => $bid->user->name ?? 'Unknown',
                    'created_at' => $bid->created_at->toISOString(),
                ];
            });

        return response()->json(['bids' => $bids]);
    }

    /**
     * Skip the current player — defer to a later round without marking as unsold.
     */
    public function skipPlayer(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        $this->undo->record(
            $auction,
            AuctionActionLog::ACTION_SKIP,
            $auctionPlayer,
            [
                'auction_player' => [
                    'status' => $auctionPlayer->status,
                    'current_price' => $auctionPlayer->current_price,
                    'current_bid_team_id' => $auctionPlayer->current_bid_team_id,
                    'final_price' => $auctionPlayer->final_price,
                ],
            ],
            'Skipped'
        );

        $auctionPlayer->update([
            'status' => 'skipped',
            'current_bid_team_id' => null,
        ]);

        // Broadcast so live page updates (reuse PlayerSoldEvent with null team)
        broadcast(new PlayerSoldEvent($auctionPlayer->fresh(), null));

        return response()->json(['success' => true, 'message' => 'Player skipped.']);
    }

    /**
     * Reverse the most recent reversible action — the safety net for a wrong-team
     * click during a live auction.
     */
    public function undoLastAction(Auction $auction)
    {
        // Paused means nothing moves, undo included — the same hold that stops bids,
        // sales and passes. Enforced here as well as on the button, because U and Ctrl+Z
        // reach this endpoint without it.
        if ($auction->status === 'paused') {
            return response()->json([
                'success' => false,
                'message' => 'The auction is paused — resume it before undoing.',
            ], 422);
        }

        $result = $this->undo->undoLast($auction);

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        $auctionPlayer = ! empty($result['auction_player_id'])
            ? AuctionPlayer::where('auction_id', $auction->id)->find($result['auction_player_id'])
            : null;

        if ($auctionPlayer) {
            // Push the restored state to the live displays.
            broadcast(new PlayerSoldEvent($auctionPlayer->fresh(), $auctionPlayer->currentBidTeam));
        }

        return response()->json($result + [
            'next_undo' => $this->undo->nextUndoable($auction)?->description,
            'next_undo_notes' => $this->undo->previewFor($auction)['notes'],
        ]);
    }

    /**
     * The player on the block, with the bid log cut to the five fields anything reads.
     *
     * Each bid was arriving as a full `AuctionBid` plus its whole `team` and `user` models, so
     * a lot with 23 raises carried 18 KB of bid history in every two-second poll — to satisfy
     * one `reduce()` over `amount` and `team_id`, and a side panel showing team, amount and
     * time. The initial page render has always projected exactly these fields; the poll did
     * not.
     *
     * `setRelation` rather than a whole new shape for `current_player`: the offline panel and
     * the organizer panel both read a dozen other fields off it, and reshaping the lot during
     * an auction to save 3 KB is not a trade worth making.
     */
    private function withTrimmedBids(?AuctionPlayer $currentPlayer): ?AuctionPlayer
    {
        if (! $currentPlayer || ! $currentPlayer->relationLoaded('bids')) {
            return $currentPlayer;
        }

        $currentPlayer->setRelation('bids', $currentPlayer->bids->map(fn ($b) => [
            'id' => $b->id,
            'amount' => $b->amount,
            'team_id' => $b->team_id,
            'team' => $b->team ? ['id' => $b->team->id, 'name' => $b->team->name] : null,
            'user' => $b->user ? ['name' => $b->user->name] : null,
            'created_at' => $b->created_at?->toISOString(),
        ])->values());

        return $currentPlayer;
    }

    /**
     * The waiting queue, as the ten fields the panel actually shows.
     *
     * This was sending full Eloquent models — every column of `auction_players` and `players`
     * plus three relations, for every waiting player — and the panel then mapped them down to
     * ten fields in the browser. On a 98-player pool that is **286 KB of the poll's 314 KB,
     * re-sent every two seconds**, to fill a list that changes when a player leaves the queue.
     *
     * That is roughly 1.2 Mbps for one open panel. On a venue running the auction over a
     * shared connection it is the difference between the room working and the room not; it
     * also makes each poll slow enough to sit in front of the bid request behind it, which is
     * the "delay before the bid shows" an operator sees.
     *
     * The initial page render has always projected exactly these fields (see the x-init in
     * organizer-panel.blade.php). The poll simply never did — one shape, described twice.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\AuctionPlayer>  $players
     * @return list<array<string, mixed>>
     */
    private function projectAvailablePlayers($players): array
    {
        return $players->map(fn ($ap) => [
            'id' => $ap->id,
            /*
             * Which pool this player is queued in.
             *
             * The panel decides who goes up next and has to obey the pool's order mode — a
             * random pool is drawn afresh on every NEXT, a sequential one is called in order.
             * Without the pool id on the row the panel could not tell which pool the queue was
             * being served from, so it treated every pool as sequential and the random setting
             * did nothing at all.
             */
            'auction_pool_id' => $ap->auction_pool_id,
            'name' => $ap->player?->name,
            'base_price' => $ap->base_price,
            'image_path' => $ap->player?->image_path,
            'player_type' => $ap->player?->playerType?->name ?? $ap->player?->playerType?->type ?? 'Player',
            // `style` first: it is the actual column. `name` does not exist on either profile
            // table, and reading it first was harmless only because of the fallback.
            'batting_style' => $ap->player?->battingProfile?->style ?? $ap->player?->battingProfile?->name,
            'bowling_style' => $ap->player?->bowlingProfile?->style ?? $ap->player?->bowlingProfile?->name,
            'is_wicket_keeper' => (bool) $ap->player?->is_wicket_keeper,
            'travel_plan_label' => $ap->player?->travel_plan_label,
            'total_matches' => $ap->player?->total_matches,
            'total_runs' => $ap->player?->total_runs,
            'total_wickets' => $ap->player?->total_wickets,
        ])->values()->all();
    }

    /** Recent actions and what Undo would reverse next, for the panel. */
    public function actionLog(Auction $auction)
    {
        return response()->json([
            'actions' => $this->undo->recentActions($auction),
            'next_undo' => $this->undo->nextUndoable($auction)?->description,
            'next_undo_notes' => $this->undo->previewFor($auction)['notes'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pool-locked auctioning
    |--------------------------------------------------------------------------
    */

    /**
     * Tell every screen that something about the auction moved.
     *
     * The pool actions changed the auction and broadcast NOTHING — not starting a pool, not
     * closing one, not reopening or restarting it. That was survivable while the public screens
     * polled every two seconds; it is not now. The wall stopped polling on a healthy socket
     * (it refetches on events instead), so a pool starting could fail to reach the hall at all
     * until some unrelated bid or sale happened to push.
     *
     * A nudge, not a payload: the screens re-read the feed, which applies the same disclosure
     * rules it always has. `AuctionStatusUpdate` is reused because both the wall and the ticker
     * already listen for it — a new event would need both of them taught about it, and this is
     * exactly what that one means: "something changed, come and look".
     */
    private function nudgeScreens(Auction $auction): void
    {
        try {
            broadcast(new AuctionStatusUpdate($auction->id, $auction->fresh()->status));
        } catch (\Throwable $e) {
            // A screen that misses the nudge recovers on its next poll or reconnect. A failed
            // broadcast must never fail the action the organizer just took.
            \Log::warning('Screen nudge failed: ' . $e->getMessage(), ['auction_id' => $auction->id]);
        }
    }

    /** Start a pool. The auction then serves only this pool until it is closed. */
    public function activatePool(Auction $auction, AuctionPool $pool)
    {
        $result = $this->pools->activatePool($auction, $pool);

        if ($result['success'] ?? false) {
            $this->nudgeScreens($auction);
        }

        return response()->json(
            $result + ['progress' => $this->pools->poolProgress($auction)],
            $result['success'] ? 200 : 422
        );
    }

    /**
     * Reopen a pool the auction has already finished with, and start it.
     *
     * Distinct from restart, which undoes that pool's sales. This keeps them and brings back
     * only the players nobody took — see AuctionPoolService::reopenPool(). The panel confirms
     * twice before calling it, because it changes which pool the auction is serving.
     */
    public function reopenPool(Auction $auction, AuctionPool $pool)
    {
        $result = $this->pools->reopenPool($auction, $pool);

        if ($result['success'] ?? false) {
            $this->nudgeScreens($auction);
        }

        return response()->json(
            $result + ['progress' => $this->pools->poolProgress($auction)],
            $result['success'] ? 200 : 422
        );
    }

    /**
     * Close the running pool. Returns the next enabled pool as a suggestion but does
     * not start it — pacing between pools stays with the organizer.
     */
    public function completePool(Auction $auction, AuctionPool $pool)
    {
        $result = $this->pools->completePool($auction, $pool);

        if ($result['success'] ?? false) {
            $this->nudgeScreens($auction);
        }

        return response()->json(
            $result + ['progress' => $this->pools->poolProgress($auction)],
            $result['success'] ? 200 : 422
        );
    }

    /**
     * Take a pool in or out of play without deleting it.
     *
     * Reached both from the live panel (JSON) and from the pools admin screen (a plain
     * form post), so the response is negotiated.
     */
    public function togglePoolEnabled(Request $request, Auction $auction, AuctionPool $pool)
    {
        if ($pool->auction_id !== $auction->id) {
            return $this->poolToggleResponse($request, false, 'That pool belongs to a different auction.', $auction);
        }

        $enabled = $request->has('is_enabled')
            ? $request->boolean('is_enabled')
            : ! $pool->isEnabled();

        // Disabling the running pool would leave the auction locked to a pool it is not
        // allowed to serve, so it must be closed first.
        if (! $enabled && $pool->isActive()) {
            return $this->poolToggleResponse(
                $request,
                false,
                sprintf('%s is currently running. Close it before disabling it.', $pool->name),
                $auction
            );
        }

        $pool->update(['is_enabled' => $enabled]);

        return $this->poolToggleResponse(
            $request,
            true,
            sprintf('%s %s.', $pool->name, $enabled ? 'enabled' : 'disabled'),
            $auction
        );
    }

    private function poolToggleResponse(Request $request, bool $success, string $message, Auction $auction)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'progress' => $this->pools->poolProgress($auction),
            ], $success ? 200 : 422);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    /*
    |--------------------------------------------------------------------------
    | Bid timer
    |--------------------------------------------------------------------------
    */

    /** Turn the countdown on or off mid-auction (offline mode only). */
    public function toggleTimer(Request $request, Auction $auction)
    {
        $enabled = $request->has('timer_enabled')
            ? $request->boolean('timer_enabled')
            : ! (bool) ($auction->timer_enabled ?? true);

        // Online bidding relies on the clock to close a round when teams stall.
        if (! $enabled && $auction->isOnlineMode()) {
            return response()->json([
                'success' => false,
                'message' => 'The timer is required while bidding is online. Switch to offline mode to turn it off.',
            ], 422);
        }

        $auction->update(['timer_enabled' => $enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Bid timer ' . ($enabled ? 'enabled' : 'disabled') . '.',
            'timer_enabled' => $enabled,
        ]);
    }

    /**
     * Called by the panel when the countdown reaches zero.
     *
     * Idempotent and re-checked against the server clock, so several open panels
     * firing at once cannot double-sell, and a browser cannot trigger it early.
     */
    public function timerExpired(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)
            ->where('auction_id', $auction->id)
            ->first();

        // Already resolved by another panel (or by hand) — nothing to do.
        if (! $auctionPlayer || $auctionPlayer->status !== 'on_auction') {
            return response()->json(['success' => true, 'message' => 'Already resolved.', 'handled' => false]);
        }

        // A sealed round owns its own clock and its own resolution. Left to the open-bid
        // path below, an expiring sealed round would be auto-sold to whoever led the
        // OPEN bidding at the threshold — skipping the sealed round entirely and handing
        // the player to a team that may not have bid at all.
        $closedBids = app(\App\Services\Auction\ClosedBidService::class);
        $round = $closedBids->currentRound($auctionPlayer);

        if ($round && ! $round->isTerminal()) {
            if ($round->state !== \App\Models\AuctionClosedBidRound::STATE_COLLECTING) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sealed round is not collecting bids.',
                    'handled' => false,
                ]);
            }

            if (! $auction->closedBidRoundTimerState($round)['expired']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sealed round still running.',
                    'handled' => false,
                ]);
            }

            /*
             * Time up HOLDS the round; it does not end it.
             *
             * This used to force a lock-and-reveal, so a clock running out with nothing submitted
             * resolved the round as "nobody entered" — and a round can easily reach zero with
             * every team still accepting, or (until recently) unable to reach the entry box at
             * all. The organizer lost the player to a countdown rather than to a decision.
             *
             * The deadline itself is unchanged: ClosedBidService::submit() still refuses a late
             * amount, so nobody gains time by this. What changes is that ending the round is now
             * an act — Lock & Reveal, or Extend to give the room longer.
             */
            return response()->json([
                'success' => true,
                'message' => 'Time up — the sealed round is held. Lock & Reveal it, or extend the clock.',
                'handled' => false,
                'action' => 'sealed_held',
            ]);
        }

        $timerState = $auction->timerStateFor($auctionPlayer);

        if (! $timerState['applies']) {
            return response()->json(['success' => true, 'message' => 'Timer is off.', 'handled' => false]);
        }

        // Trust the server clock, not the caller.
        if (! $timerState['expired']) {
            return response()->json([
                'success' => true,
                'message' => 'Timer still running.',
                'handled' => false,
                'seconds_remaining' => $timerState['remaining'],
            ]);
        }

        if (! $auction->timerAutoSells()) {
            /*
             * Manual mode: the clock is an ANNOUNCEMENT to the operator, not a lock on them.
             *
             * This used to say "bidding is closed", which was true when addBid() refused an
             * expired bid from the organizer as well as from a team. It no longer does — the
             * countdown exists to stop teams stalling, and when it runs out the person running
             * the room is the one who has to record what the room already heard. Teams are
             * still locked out (AuctionBiddingController::placeBid), so the room's clock is
             * unchanged; only the message was lying to the operator.
             */
            return response()->json([
                'success' => true,
                'message' => "Time up. Sell, pass or skip — you can still take bids if the room is not finished.",
                'handled' => true,
                'action' => 'locked',
            ]);
        }

        // auto_sell: award to the highest standing bidder. Nothing more.
        $winningBid = $auctionPlayer->liveBids()->orderByDesc('amount')->first();

        if (! $winningBid || ! $winningBid->team_id) {
            /*
             * The clock does NOT set a player unsold.
             *
             * It used to pass them automatically the moment it ran out with no bids — moving
             * them off the block and into the unsold pile before anyone in the room had
             * finished speaking. In a hall the last seconds are exactly when a hand goes up,
             * and recovering meant an UNDO that also had to be explained to the auctioneer.
             *
             * Auto-sell means what it says: if there is a bid, award it. An absence of bids is
             * not a result the clock is entitled to record — that is a judgement about whether
             * the room is finished, and only the person running it can make it. So the player
             * stays on the block and the panel says so; PASS is one key away.
             */
            return response()->json([
                'success' => true,
                'message' => 'Time up with no bids. The player is still on the block — press PASS to set them aside, or keep taking bids.',
                'handled' => true,
                'action' => 'locked',
            ]);
        }

        $sellRequest = new Request(['auction_player_id' => $auctionPlayer->id]);
        $response = $this->sellPlayer($sellRequest, $auction);
        $payload = $response->getData(true);

        return response()->json([
            'success' => $payload['success'] ?? true,
            'message' => $payload['message'] ?? 'Sold on the timer.',
            'handled' => true,
            'action' => ($payload['success'] ?? true) ? 'sold' : 'blocked',
        ], $response->getStatusCode());
    }

    /**
     * Start a re-auction round — reset all unsold + skipped players back to waiting.
     */
    public function startReAuctionRound(Request $request, Auction $auction)
    {
        // Check no player is currently live
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Finish the current player before starting a new round.',
            ], 400);
        }

        $affected = $auction->auctionPlayers()
            ->whereIn('status', ['unsold', 'skipped'])
            ->count();

        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No unsold or skipped players to re-auction.',
            ], 400);
        }

        $repooled = 0;

        DB::transaction(function () use ($auction, &$repooled) {
            // Capture the ids BEFORE flipping their status. Re-querying for
            // status='waiting' after the update also matched players who were
            // already waiting, so their bid history was deleted too.
            $resetPlayers = $auction->auctionPlayers()
                ->whereIn('status', ['unsold', 'skipped'])
                ->with('pool')
                ->get();

            $auction->auctionPlayers()
                ->whereIn('status', ['unsold', 'skipped'])
                ->update([
                    'status' => 'waiting',
                    'current_price' => DB::raw('base_price'),
                    'current_bid_team_id' => null,
                    'sold_to_team_id' => null,
                    'final_price' => null,
                ]);

            AuctionBid::where('auction_id', $auction->id)
                ->whereIn('auction_player_id', $resetPlayers->pluck('id'))
                ->delete();

            // Players sitting in an unsold holding pool go back to the pool they came
            // from, otherwise they would be waiting inside a pool the auction never
            // serves. Pools touched this way get their lots redrawn.
            $poolsToRedraw = [];
            foreach ($resetPlayers as $player) {
                $pool = $player->pool;
                if (! $pool?->isUnsoldPool()) {
                    continue;
                }

                /*
                 * The player's own recorded origin first, the holding pool's parent second.
                 *
                 * Unsold players now share one pile per auction, which has no parent — reading
                 * only the parent would have skipped every one of them and left them waiting
                 * inside a pool the auction never serves. The fallback covers a player set
                 * aside before source_pool_id existed and never backfilled.
                 */
                $target = $player->source_pool_id ?: $pool->parent_pool_id;

                if (! $target) {
                    continue;
                }

                $player->update([
                    'auction_pool_id' => $target,
                    'source_pool_id' => null,
                    'lot_number' => null,
                ]);
                $poolsToRedraw[$target] = true;
                $repooled++;
            }

            foreach (array_keys($poolsToRedraw) as $poolId) {
                if ($target = AuctionPool::find($poolId)) {
                    $this->pools->generateLotNumbers($target);
                    // A pool with players again is startable again.
                    $target->update([
                        'status' => AuctionPool::STATUS_PENDING,
                        'completed_at' => null,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => $affected . ' player(s) moved back to waiting for re-auction.'
                . ($repooled ? sprintf(' %d returned from unsold pools.', $repooled) : ''),
            'reset_count' => $affected,
            'repooled_count' => $repooled,
        ]);
    }

    /**
     * Re-bid the current player — reset price/bids and restart bidding.
     */
    public function rebidPlayer(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        // If the player had already been sold, unwind their team attachment before
        // putting them back on the block.
        if ($auctionPlayer->status === 'sold') {
            $this->sales->clearTeamAttachment($auctionPlayer);
        }

        // Close off any sealed round. Marked abandoned rather than deleted: this method
        // wipes bids and action logs, so the round row is the only durable record of who
        // bid what in a round that may later be disputed.
        app(\App\Services\Auction\ClosedBidService::class)->abandonRoundsFor($auctionPlayer);

        DB::transaction(function () use ($auctionPlayer, $auction) {
            // Reset player price and bids
            $auctionPlayer->update([
                'status' => 'on_auction',
                'current_price' => $auctionPlayer->base_price,
                'current_bid_team_id' => null,
                'final_price' => null,
                'sold_to_team_id' => null,
            ]);

            // Delete bids for this player
            AuctionBid::where('auction_id', $auction->id)
                ->where('auction_player_id', $auctionPlayer->id)
                ->delete();

            // Bids are gone, so any logged action referring to them can no longer be
            // meaningfully reversed — drop them from the undo stack.
            AuctionActionLog::where('auction_id', $auction->id)
                ->where('auction_player_id', $auctionPlayer->id)
                ->delete();

            // Reset player_mode in case it was retained
            Player::where('id', $auctionPlayer->player_id)
                ->update(['player_mode' => 'normal']);

            // The sealed phase resets with the player; the room's online/offline mode is
            // not a per-player fact and is left as the organizer set it.
            if ($auction->hasAutoPhaseTransition()) {
                $auction->update([
                    'bid_type' => 'open',
                    'bid_type_manually_overridden' => false,
                ]);
            }

            // Restart the clock, exactly as putting a player on the block does. Without
            // this a re-bid inherited the previous player's elapsed timer — already
            // expired, so bidding was closed (or the player auto-sold) the instant they
            // went back up. When the timer is switched off there is simply no clock and
            // the player is shown for open bidding.
            $auction->update(['timer_started_at' => now()]);
        });

        $playerDataForBroadcast = $auctionPlayer->fresh([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'bids.team',
            'bids.user',
        ]);

        broadcast(new PlayerOnBid($playerDataForBroadcast));
        return response()->json(['success' => true, 'message' => 'Player re-bid started.']);
    }

    /**
     * Get all auction players with their statuses for the "All Players" tab.
     */
    public function allPlayers(Auction $auction)
    {
        // Ordered in PHP rather than with MySQL's FIELD(), which is not portable
        // (the repo also ships an sqlite database).
        $statusOrder = ['on_auction' => 0, 'waiting' => 1, 'skipped' => 2, 'sold' => 3, 'unsold' => 4];

        $players = $auction->auctionPlayers()
            ->with(['player.playerType', 'soldToTeam'])
            ->get()
            ->sortBy(fn ($ap) => $statusOrder[$ap->status] ?? 99)
            ->values()
            ->map(fn ($ap) => [
                'id' => $ap->id,
                'name' => $ap->player->name ?? 'Unknown',
                'status' => $ap->status,
                'sold_to_team' => $ap->soldToTeam?->name,
                'final_price' => $ap->final_price,
                'base_price' => $ap->base_price,
                'image_path' => $ap->player->image_path ?? null,
                'player_type' => $ap->player->playerType?->name ?? null,
                'total_matches' => $ap->player->total_matches,
                'total_runs' => $ap->player->total_runs,
                'total_wickets' => $ap->player->total_wickets,
            ]);

        return response()->json(['players' => $players]);
    }

    /**
     * Re-auction a sold or unsold player — put them back on auction.
     */
    public function reAuctionPlayer(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->whereIn('status', ['sold', 'unsold', 'skipped'])
            ->firstOrFail();

        // Check no other player is currently live
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Finish the current player first before re-auctioning another.',
            ], 400);
        }

        // A sold player must be fully detached from their team, not just have
        // player_mode reset — otherwise they stay on the buyer's squad and roster
        // while being auctioned again.
        if ($auctionPlayer->status === 'sold') {
            $this->sales->clearTeamAttachment($auctionPlayer);
        }

        // Any sealed round from the previous attempt is closed off. A fresh attempt gets
        // its own round numbered from 1 again, and the old record is kept.
        app(\App\Services\Auction\ClosedBidService::class)->abandonRoundsFor($auctionPlayer);

        DB::transaction(function () use ($auctionPlayer, $auction) {
            // Reset and put on auction
            $auctionPlayer->update([
                'status' => 'on_auction',
                'current_price' => $auctionPlayer->base_price,
                'current_bid_team_id' => null,
                'sold_to_team_id' => null,
                'final_price' => null,
            ]);

            // Delete old bids and the matching undo entries.
            AuctionBid::where('auction_id', $auction->id)
                ->where('auction_player_id', $auctionPlayer->id)
                ->delete();

            AuctionActionLog::where('auction_id', $auction->id)
                ->where('auction_player_id', $auctionPlayer->id)
                ->delete();

            // The sealed phase resets with the player; the room's online/offline mode is
            // not a per-player fact and is left as the organizer set it.
            if ($auction->hasAutoPhaseTransition()) {
                $auction->update([
                    'bid_type' => 'open',
                    'bid_type_manually_overridden' => false,
                ]);
            }

            // Restart the clock, exactly as putting a player on the block does. Without
            // this a re-bid inherited the previous player's elapsed timer — already
            // expired, so bidding was closed (or the player auto-sold) the instant they
            // went back up. When the timer is switched off there is simply no clock and
            // the player is shown for open bidding.
            $auction->update(['timer_started_at' => now()]);
        });

        $playerDataForBroadcast = $auctionPlayer->fresh([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'bids.team',
            'bids.user',
        ]);

        broadcast(new PlayerOnBid($playerDataForBroadcast));
        return response()->json(['success' => true, 'message' => 'Player put back on auction.']);
    }

    /**
     * Update a player's base price from the offline panel.
     */
    public function updateAuctionBasePrice(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'base_price' => 'required|numeric|min:0',
        ]);

        $auction->update([
            'base_price' => $validated['base_price'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Auction base price updated.',
            'base_price' => $auction->base_price,
        ]);
    }

    public function updateBasePrice(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'base_price' => 'required|numeric|min:0',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        $auctionPlayer->update([
            'base_price' => $validated['base_price'],
        ]);

        // If player is currently on auction and has no bids, also update current_price
        if ($auctionPlayer->status === 'on_auction' && ! $auctionPlayer->current_bid_team_id) {
            $auctionPlayer->update([
                'current_price' => $validated['base_price'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Base price updated.',
            'base_price' => $auctionPlayer->base_price,
        ]);
    }

    /**
     * Send sold notifications to player and team managers.
     */
    /**
     * Raise the "you've been sold" notification.
     *
     * Handed to AuctionMailService rather than sent here: this used to fire an SMTP send
     * inline on every sale, so the room waited on the mail server, and a rehearsal run
     * mailed real players.
     */
    private function notifyPlayerSold(int $playerId, ActualTeam $team, Auction $auction, float $finalPrice): void
    {
        $auctionPlayer = AuctionPlayer::where('auction_id', $auction->id)
            ->where('player_id', $playerId)
            ->first();

        $this->mail->raise(
            $auction,
            AuctionPendingEmail::TYPE_SOLD,
            $auctionPlayer,
            $team,
            ['amount' => $finalPrice]
        );
    }
}
