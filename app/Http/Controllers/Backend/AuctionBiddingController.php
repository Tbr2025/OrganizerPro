<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionPlayer;
use App\Models\AuctionBid;
use App\Models\ActualTeam;
use App\Models\AuctionActionLog;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\BidIncrementService;
use App\Services\Auction\ClosedBidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuctionBiddingController extends Controller
{
    public function __construct(
        private readonly AuctionPoolService $pools,
        private readonly AuctionUndoService $undo,
        private readonly BidIncrementService $increments,
    ) {
    }

    /**
     * Display the Team Manager's bidding page.
     */
    public function showBiddingPage(Request $request, Auction $auction)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isPreviewMode = false;
        $allTeams = collect();

        // Check if user is Admin/Superadmin for preview mode
        $isAdmin = $user->hasRole(['Superadmin', 'Admin']);

        if ($isAdmin) {
            // Admin preview mode - get team from query param or show team selector
            $teamId = $request->query('team_id');

            if ($teamId) {
                // Admin viewing as specific team
                $userTeam = ActualTeam::where('id', $teamId)
                    ->forTournament($auction->tournament_id)
                    ->first();

                if (! $userTeam) {
                    abort(404, 'Team not found in this tournament.');
                }

                $isPreviewMode = true;
            } else {
                /*
                 * The teams that are actually IN this auction, with the figures that matter.
                 *
                 * `ActualTeam::forTournament()` alone listed every side the tournament has a row
                 * for — pending registrations included — which is the same mistake the sealed
                 * board had: a team nobody approved appearing with a purse beside it.
                 * `participatingTeams()` is the one definition of who may spend money here, and
                 * the organizer panel and the ticker already use it.
                 */
                $allTeams = $this->pools->participatingTeams($auction);

                /*
                 * And the counts the screen was getting wrong.
                 *
                 * It showed `$team->players()->count()`, which is hasMany(ActualTeamUser) —
                 * EVERY membership row regardless of role. Squad of Cuba read "6 players" for
                 * four players, a manager and an owner. Worse, it was not scoped to this
                 * tournament and knew nothing of the auction, so it could not answer the
                 * question actually being asked at this screen: how far through is this squad.
                 */
                $allTeams = $allTeams->map(function (ActualTeam $team) use ($auction) {
                    $purse = $this->pools->teamPurseState($auction, $team->id);

                    $team->setAttribute('squad_filled', $purse['slots_filled']);
                    $team->setAttribute('squad_required', $purse['slots_required']);
                    $team->setAttribute('squad_remaining', $purse['slots_remaining']);
                    $team->setAttribute('purse_remaining', $purse['remaining']);
                    $team->setAttribute('retained_count', $purse['retained_count'] ?? 0);

                    return $team;
                });

                $breadcrumbs = ['title' => __('Select Team')];

                return view('backend.pages.auction.bidding-team-selector', compact(
                    'auction',
                    'allTeams',
                    'breadcrumbs'
                ));
            }
        } else {
            // Only team managers/coaches/captains can access the bidding page — not players
            if ($user->hasRole('player')) {
                abort(403, 'Players cannot access the bidding page. Only team managers can bid.');
            }

            // Regular team manager - find their team
            $userTeam = $user->actualTeams()
                ->forTournament($auction->tournament_id)
                ->first();

            // Security: Abort if the user is not on a participating team.
            if (! $userTeam) {
                abort(403, 'Your team is not a participant in this tournament\'s auction.');
            }
        }

        // Purse state from the one canonical implementation: honours per-team
        // budget allocations, retained-player cost and the squad reserve. (The old
        // inline sum here also omitted the status='sold' filter, so it counted
        // players who were merely on the block.)
        $purse = $this->pools->teamPurseState($auction, $userTeam->id);
        // `?:` here treated a deliberate per-team allocation of 0 as "unset" and fell
        // back to the auction-wide cap, handing a zero-budget team the full purse.
        $maxBudget = $this->cap($purse['allocated']);
        $remainingBudget = $this->cap($purse['remaining']);
        $maxBidAllowed = $this->cap($purse['max_bid_allowed']);

        // Get the initial state of the auction for the view.
        $auctionPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',

                // Constrained to live bids: the log is append-only, so a retracted
                // (undone) bid is still present and must not appear as a standing bid.
                'bids' => fn ($q) => $q->where('is_void', false)->with(['team', 'user']),
                'currentBidTeam',
            ])
            ->first();

        // Format the current player data to match API format
        $currentPlayer = null;
        if ($auctionPlayer) {
            $playerData = $auctionPlayer->player->toArray();
            $playerData['player_type'] = $auctionPlayer->player->playerType;
            $playerData['batting_profile'] = $auctionPlayer->player->battingProfile;
            $playerData['bowling_profile'] = $auctionPlayer->player->bowlingProfile;

            $currentPlayer = [
                'id' => $auctionPlayer->id,
                'player' => $playerData,
                'base_price' => $auctionPlayer->base_price,
                'current_price' => $auctionPlayer->current_price,
                'current_bid_team' => $auctionPlayer->currentBidTeam ? [
                    'id' => $auctionPlayer->currentBidTeam->id,
                    'name' => $auctionPlayer->currentBidTeam->name,
                ] : null,
                // Only this team's own bids. The page used to embed every team's live
                // bid rows — id, amount and team name — straight into the HTML, so any
                // team manager could read the whole board from view-source.
                'bids' => $auctionPlayer->bids
                    ->when($userTeam, fn ($bids) => $bids->where('team_id', $userTeam->id))
                    ->map(function ($bid) {
                        return [
                            'id' => $bid->id,
                            'amount' => $bid->amount,
                            'team' => $bid->team ? [
                                'id' => $bid->team->id,
                                'name' => $bid->team->name,
                            ] : null,
                        ];
                    })->values()->toArray(),
                'status' => $auctionPlayer->status,
            ];
        }

        // Next price this player would go for, so teams priced out under the
        // reserve rule can be shown as excluded.
        $nextBid = $auctionPlayer
            ? $this->increments->nextBidAmount($auction, (float) $auctionPlayer->current_price)
            : null;

        // Get all teams with their budgets
        $allTeams = ActualTeam::forTournament($auction->tournament_id)
            ->get()
            ->map(function ($team) use ($auction, $nextBid) {
                $state = $this->pools->teamPurseState($auction, $team->id, $nextBid);
                $team->spent = $state['spent'];
                $team->remaining = $this->cap($state['remaining']);
                $team->max_bid_allowed = $this->cap($state['max_bid_allowed']);
                $team->reserve_amount = $state['reserve'];
                $team->players_count = $state['slots_filled'];
                $team->slots_required = $state['slots_required'];
                $team->slots_remaining = $state['slots_remaining'];
                $team->excluded = $state['excluded'];
                return $team;
            });

        // Get sold players
        $soldPlayers = $auction->auctionPlayers()
            ->with(['player', 'soldToTeam'])
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get user's latest bid for current player
        $myBid = null;
        if ($auctionPlayer) {
            $myBid = AuctionBid::where('auction_id', $auction->id)
                ->live()
                ->where('auction_player_id', $auctionPlayer->id)
                ->where('team_id', $userTeam->id)
                ->latest('amount')
                ->first();
        }

        // Return the view with all necessary data.
        return view('backend.pages.auction.bidding-page', compact(
            'auction',
            'userTeam',
            'currentPlayer',
            'isPreviewMode',
            'remainingBudget',
            'maxBudget',
            'maxBidAllowed',
            'purse',
            'allTeams',
            'soldPlayers',
            'myBid'
        ));
    }

    /**
     * Authenticated purse poll for the bidding page.
     *
     * The public /auction/{id}/active-player feed is unauthenticated and carries no
     * team data, so the bidding page had no way to refresh its own budget — it read
     * the figure once at render and went stale the moment the team won a player.
     */
    public function pursePoll(Request $request, Auction $auction)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $userTeam = $user->actualTeams()->forTournament($auction->tournament_id)->first();
        if (! $userTeam) {
            return response()->json(['error' => 'You are not assigned to a team in this tournament.'], 403);
        }

        $auctionPlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        $nextBid = $auctionPlayer
            ? $this->increments->nextBidAmount($auction, (float) $auctionPlayer->current_price)
            : null;

        $purse = $this->pools->teamPurseState($auction, $userTeam->id, $nextBid);

        return response()->json([
            'success' => true,
            'team_id' => $userTeam->id,
            'excluded' => $purse['excluded'],
            'next_bid_amount' => $nextBid,
        ] + $this->pursePayload($purse));
    }

    /**
     * Handle a "Raise Hand" bid from a Team Manager (IPL-style).
     * For open bid: auto-increments based on bid rules (no custom amount).
     * For closed bid: accepts a custom amount.
     */
    public function placeBid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'amount' => 'nullable|numeric|min:0', // Only used for closed bid
        ]);

        // No bids while the auction is paused.
        if ($auction->status === 'paused') {
            return response()->json(['error' => 'The auction is paused. Please wait for the organizer to resume.'], 423);
        }

        $user = Auth::user();

        // Only team managers can place bids — not players
        if ($user->hasRole('player')) {
            return response()->json(['error' => 'Players cannot place bids. Only team managers can bid.'], 403);
        }

        // Scope to a team in THIS auction's tournament. Taking the first team the
        // user manages let someone who manages teams in several tournaments bid as
        // the wrong team entirely.
        $userTeam = $user->actualTeams()->forTournament($auction->tournament_id)->first();
        if (! $userTeam) {
            return response()->json(['error' => 'You are not assigned to a team in this tournament.'], 403);
        }

        // Reject bids when auction is in offline mode (applies to any bid_type)
        $freshAuction = $auction->fresh();
        if ($freshAuction->open_bid_mode === 'offline') {
            return response()->json([
                'error' => 'Bidding is currently in offline mode. The organizer is handling bids manually.',
            ], 422);
        }

        // The countdown is now enforced, not decorative: a bid arriving after the
        // clock ran out is refused rather than silently extending the round.
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer && $freshAuction->timerStateFor($livePlayer)['expired']) {
            return response()->json([
                'error' => 'Time is up for this player. Bidding is closed.',
            ], 422);
        }

        // Sealed rounds take a completely different path. A closed bid used to be an
        // ordinary bid row that publicly raised current_price and stamped
        // current_bid_team_id — both of which are served by the UNAUTHENTICATED
        // active-player feed, so the top sealed amount and the team behind it were
        // visible to every rival within one poll. The sealed amount now goes to the
        // round's entries, and nothing public moves.
        if ($auction->bid_type === 'closed') {
            return $this->submitSealedBid($request, $auction, $userTeam, $validated);
        }

        $newPrice = null;
        $raise = null;

        try {
            DB::transaction(function () use ($validated, $userTeam, $auction, &$newPrice, &$raise) {
                $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
                    ->where('auction_id', $auction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($auctionPlayer->status !== 'on_auction') {
                    throw new \Exception('Bidding is not active for this player.');
                }

                if ($auctionPlayer->current_bid_team_id === $userTeam->id) {
                    throw new \Exception('Your team is already the highest bidder.');
                }

                $current = (float) $auctionPlayer->current_price;

                // Open bidding only: sealed rounds never reach here. The server sets
                // the amount from the increment ladder, so a client cannot name it.
                $bidAmount = $this->increments->nextBidAmount($auction, $current);

                if ($bidAmount === null) {
                    throw new \Exception($this->increments->noIncrementReason($auction, $current));
                }

                // Budget + squad-reserve validation, via the one canonical
                // implementation. The old inline sum ignored per-team budget
                // allocations and retained-player cost, so it disagreed with the
                // check applied at SELL — a team could bid freely and only be
                // blocked once the hammer came down.
                if (! $this->pools->canAffordWithReserve($auction, $userTeam->id, $bidAmount)) {
                    throw new \Exception(
                        $this->pools->reserveBlockedMessage($auction, $userTeam->id, $bidAmount, 'Your team')
                    );
                }

                // Create a new bid record
                $bid = AuctionBid::create([
                    'auction_id' => $auction->id,
                    'auction_player_id' => $auctionPlayer->id,
                    'team_id' => $userTeam->id,
                    'player_id' => $auctionPlayer->player_id,
                    'user_id' => auth()->id(),
                    'amount' => $bidAmount,
                    'bid_source' => 'online',
                ]);

                // Update highest bid on the auction player
                if ($bidAmount > $auctionPlayer->current_price) {
                    $auctionPlayer->update([
                        'current_price' => $bidAmount,
                        'current_bid_team_id' => $userTeam->id,
                    ]);
                }

                // Log it so the organizer can undo a bid placed in error.
                $this->undo->record(
                    $auction,
                    AuctionActionLog::ACTION_BID,
                    $auctionPlayer,
                    [
                        'bid_id' => $bid->id,
                        'amount' => $bidAmount,
                        'team_id' => $userTeam->id,
                        'team_name' => $userTeam->name,
                        'previous_price' => $current,
                        'previous_team_id' => $auctionPlayer->getOriginal('current_bid_team_id'),
                    ],
                    sprintf('Bid %s by %s', format_points($bidAmount), $userTeam->name)
                );

                // A successful bid restarts the clock for the next raise.
                $auction->update(['timer_started_at' => now()]);

                $newPrice = $bidAmount;

                // Carried out of the transaction (by reference) so the raise can be
                // announced only once it has actually committed.
                $raise = [
                    'player' => $auctionPlayer,
                    'bid_id' => $bid->id,
                ];

                // One rule, on the model. Reaching the sealed threshold also opens the
                // round, so there is a single place where that can happen.
                $freshAuction = Auction::find($auction->id);
                $phase = $freshAuction->applyAutoPhase($bidAmount);

                if ($phase['bid_type_changed']) {
                    app(\App\Services\Auction\ClosedBidService::class)
                        ->openRoundFor($auctionPlayer->fresh(), $freshAuction->fresh());
                }
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        /*
         * Announce the raise, after the commit.
         *
         * Every other screen learned about a bid only when its own poll came round — up to
         * two seconds, plus a second of feed cache — so a team could bid against a price
         * roughly three seconds old. This is the push that closes that window; the polls
         * stay exactly as they are and remain the reconciliation path.
         *
         * Outside the transaction on purpose: broadcasting inside it would publish a raise
         * that a later rollback erases, and there is no way to un-send it.
         */
        if ($raise !== null) {
            /*
             * And after the response, not before it. ShouldBroadcastNow calls Pusher inline —
             * ~1160ms for the handshake this request has to make from scratch — so a team
             * tapping BID waited on that before their own screen said it had gone through.
             * The push still leaves at the same moment for everyone else.
             */
            \App\Support\AfterResponse::run(function () use ($raise, $userTeam) {
                \App\Events\BidRaised::announce($raise['player'], $raise['bid_id'], $userTeam->name);
            });
        }

        // Hand the fresh purse state back so the bidding page can update its
        // budget, squad count and max-allowed-bid without a page reload — it used
        // to seed teamBudget once at render and never refresh it.
        $purse = $this->pools->teamPurseState($auction, $userTeam->id);

        return response()->json([
            'success' => 'Bid placed successfully.',
            'new_price' => $newPrice,
            'team_name' => $userTeam->name,
        ] + $this->pursePayload($purse));
    }

    /*
    |--------------------------------------------------------------------------
    | Sealed rounds (team side)
    |--------------------------------------------------------------------------
    | The team is always taken from the session, never from the request, and an admin
    | previewing a team's screen is read-only — previewing must not let somebody submit
    | another team's sealed bid.
    */

    /**
     * The requesting user's team in THIS auction's tournament.
     *
     * Always from the session, never from the request. Scoped to the tournament because
     * somebody managing teams in several tournaments would otherwise bid as the wrong one.
     */
    /**
     * Which team this request speaks for.
     *
     * showBiddingPage() has always honoured `?team_id=` so an admin can look at the auction
     * through a team's eyes — but this did not, so every poll behind that page answered as
     * "no team at all". A superadmin previewing a team belongs to none, so the sealed state came
     * back with no entry, `invited: false`, and the page told an invited team that the round was
     * between the teams the organizer had selected. The sealed box could never appear in preview.
     *
     * The preview path is gated on the admin roles exactly as the page is, so a team manager
     * cannot read another team's round by adding a query parameter. Actions are unaffected:
     * sealedAction() refuses preview outright, and placeBid() has its own check — this only
     * decides whose state is being READ.
     */
    private function resolveTeam(Request $request, Auction $auction)
    {
        $user = Auth::user();
        $previewTeamId = $request->query('team_id') ?: session('auction_preview_team_id');

        if ($previewTeamId && $user?->hasRole(['Superadmin', 'Admin'])) {
            $team = ActualTeam::whereKey($previewTeamId)
                ->forTournament($auction->tournament_id)
                ->first();

            if ($team) {
                return $team;
            }
        }

        return $user?->actualTeams()->forTournament($auction->tournament_id)->first();
    }

    /** The player on the block, with its sealed round resolved. */
    private function sealedTarget(Auction $auction, ?int $auctionPlayerId = null): ?AuctionPlayer
    {
        $query = AuctionPlayer::where('auction_id', $auction->id);

        return $auctionPlayerId
            ? $query->find($auctionPlayerId)
            : $query->where('status', 'on_auction')->first();
    }

    /** Sealed-round state for the requesting team. */
    public function closedBidState(Request $request, Auction $auction)
    {
        $userTeam = $this->resolveTeam($request, $auction);
        $player = $this->sealedTarget($auction);

        return response()->json([
            'success' => true,
            'sealed' => app(ClosedBidService::class)->stateForTeam($auction, $player, $userTeam?->id),
        ]);
    }

    /** Accept the round conditions and enter. */
    public function acceptClosedBid(Request $request, Auction $auction)
    {
        return $this->sealedAction($request, $auction, function ($service, $round, $team) {
            return $service->accept($round, $team, auth()->user());
        });
    }

    /** Leave the round without bidding. */
    public function declineClosedBid(Request $request, Auction $auction)
    {
        return $this->sealedAction($request, $auction, function ($service, $round, $team) {
            return $service->decline($round, $team);
        });
    }

    /** Record this team's sealed amount. */
    public function submitClosedBid(Request $request, Auction $auction)
    {
        $validated = $request->validate(['amount' => 'required|numeric|min:0']);

        return $this->sealedAction($request, $auction, function ($service, $round, $team, $source) use ($validated) {
            return $service->submit($round, $team, (float) $validated['amount'], auth()->user(), $source);
        });
    }

    /** Withdraw this team from the round. */
    public function withdrawClosedBid(Request $request, Auction $auction)
    {
        return $this->sealedAction($request, $auction, function ($service, $round, $team) {
            $entry = $round->entries()->where('actual_team_id', $team->id)->first();

            if (! $entry) {
                return ['handled' => false, 'message' => 'Your team is not in this round.'];
            }

            return $service->withdraw($entry, auth()->user(), AuctionClosedBidEntry::ROLE_TEAM);
        });
    }

    /** Re-enter after withdrawing. */
    public function reinstateClosedBid(Request $request, Auction $auction)
    {
        return $this->sealedAction($request, $auction, function ($service, $round, $team) {
            $entry = $round->entries()->where('actual_team_id', $team->id)->first();

            if (! $entry) {
                return ['handled' => false, 'message' => 'Your team is not in this round.'];
            }

            return $service->reinstate($entry, auth()->user(), AuctionClosedBidEntry::ROLE_TEAM);
        });
    }

    /**
     * The checks every team-side sealed action shares.
     *
     * These routes carry only `auth` — the permission work is done here, exactly as
     * placeBid() does it, so a new endpoint cannot quietly skip a check the old one had.
     */
    private function sealedAction(Request $request, Auction $auction, callable $do)
    {
        if ($auction->status === 'paused') {
            return response()->json(['error' => 'The auction is paused.'], 423);
        }

        $user = auth()->user();

        if ($user?->hasRole('player')) {
            return response()->json(['error' => 'Players cannot place bids.'], 403);
        }

        /*
         * Offline does NOT lock a team out of a sealed round.
         *
         * "Offline" describes OPEN bidding: the organizer calls the room aloud and enters the
         * raises, and placeBid() still refuses a team's own open bid for that reason. A sealed
         * round is the opposite kind of thing — a single private number, entered without seeing
         * anyone else's — and that is exactly what a manager should type on their own device even
         * in a room-called auction. Forcing the organizer to collect six sealed amounts by hand
         * defeats the privacy the round exists for, and it was the one thing teams could not do.
         *
         * The organizer can still enter on a team's behalf; both doors are open on purpose here,
         * and every other guard still applies — invitation, acceptance, the deadline, the
         * ceilings, and one final bid per team per round.
         */

        /*
         * Read-only preview: an admin looking at a team's screen must not act as them.
         *
         * The check used to test only `?preview` and the session flag — not `team_id`, which is
         * how the page is actually opened. So an admin previewing a team fell straight past this
         * and into the team-resolution failure below, and was told "your team is not in this
         * tournament" — which is not what happened and points nowhere useful.
         *
         * The rule itself stands. A bid a team did not make must not enter a sealed round
         * unattributed, and the organizer already has a legitimate path for entering on a team's
         * behalf: the panel's own control, recorded as an admin adjustment and undoable.
         */
        /*
         * An organizer looking at a team's screen may act — ATTRIBUTED AS THE ORGANIZER.
         *
         * This refused outright, on the reasoning that a bid a team did not make must not enter a
         * sealed round. The reasoning is right; the remedy was not. The organizer has always been
         * able to enter on a team's behalf from the panel, so refusing the same act by the same
         * person on a different screen bought no integrity — it just sent them to another tab, and
         * in a room where a manager cannot reach their own screen it sent them there mid-round.
         *
         * What matters is that the act is attributed and reversible, so it is recorded as
         * ROLE_ADMIN exactly as the panel's own control is: it lands in the entry's adjustment
         * trail and it can be undone. A team's own submission stays ROLE_TEAM.
         */
        $isPreview = $request->query('preview')
            || session('auction_preview_team_id')
            || ($request->query('team_id') && $user?->hasRole(['Superadmin', 'Admin']));

        $actingAsOrganizer = $isPreview && (bool) $user?->hasRole(['Superadmin', 'Admin']);

        if ($isPreview && ! $actingAsOrganizer) {
            return response()->json(['error' => 'Preview mode is read-only.'], 403);
        }

        $userTeam = $this->resolveTeam($request, $auction);

        if (! $userTeam) {
            return response()->json([
                'error' => $user?->hasRole(['Superadmin', 'Admin'])
                    // An admin with no team of their own is the common case here, and "your team"
                    // is a confusing way to describe it.
                    ? 'Open this from a team\'s screen, or use the organizer panel, to enter an amount.'
                    : 'Your team is not in this tournament.',
            ], 403);
        }

        $player = $this->sealedTarget($auction);
        $service = app(ClosedBidService::class);
        $round = $player ? $service->currentRound($player) : null;

        if (! $round || $round->isTerminal()) {
            return response()->json(['error' => 'No sealed round is open for this player.'], 422);
        }

        $result = $do($service, $round, $userTeam, $actingAsOrganizer
            ? AuctionClosedBidEntry::ROLE_ADMIN
            : AuctionClosedBidEntry::ROLE_TEAM);

        if (! ($result['handled'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'That is not possible right now.'], 422);
        }

        $purse = $this->pools->teamPurseState($auction, $userTeam->id);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? null,
            'sealed' => $service->stateForTeam($auction, $player->fresh(), $userTeam->id),
        ] + $this->pursePayload($purse));
    }

    /** Sealed submission arriving on the legacy place-bid endpoint. */
    private function submitSealedBid(Request $request, Auction $auction, $userTeam, array $validated)
    {
        if (! isset($validated['amount']) || $validated['amount'] <= 0) {
            return response()->json(['error' => 'A bid amount is required in a sealed round.'], 422);
        }

        return $this->sealedAction($request, $auction, function ($service, $round, $team, $source) use ($validated) {
            return $service->submit($round, $team, (float) $validated['amount'], auth()->user(), $source);
        });
    }

    /**
     * Purse figures shaped for the bidding page's Alpine state.
     *
     * @param  array<string, mixed>  $purse
     * @return array<string, mixed>
     */
    private function pursePayload(array $purse): array
    {
        return [
            'remaining_budget' => $this->cap($purse['remaining']),
            'max_bid_allowed' => $this->cap($purse['max_bid_allowed']),
            // The reserve maximum and the per-player ceiling, and the lower of the two — which
            // is the only one a manager actually has to obey. `open_per_player_cap_pct` is null
            // when no ceiling is configured, which the page uses to say nothing about one.
            'open_max_bid' => $this->cap($purse['open_max_bid']),
            'open_per_player_cap' => $this->cap($purse['open_per_player_cap']),
            'open_per_player_cap_pct' => $purse['open_per_player_cap_pct'],
            'reserve_amount' => $purse['reserve'],
            'slots_filled' => $purse['slots_filled'],
            'slots_required' => $purse['slots_required'],
            'slots_remaining' => $purse['slots_remaining'],
            'allocated' => $this->cap($purse['allocated']),
            'auction_purse' => $this->cap($purse['auction_purse']),
            'retained_spent' => $purse['retained_spent'],
            'auction_spent' => $purse['auction_spent'],
            'retained_count' => $purse['retained_count'],
            'retained_expected' => $purse['retained_expected'],
            'slots_max' => $purse['slots_max'],
        ];
    }

    /**
     * Open tournaments have no budget cap and report PHP_FLOAT_MAX, which does not
     * survive JSON encoding. Clamp it to a large finite number for the client.
     */
    private function cap(float $value): float
    {
        return $value >= 1.0e15 ? 1.0e15 : $value;
    }
}
