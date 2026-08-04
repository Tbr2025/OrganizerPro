<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionBid;
use App\Models\ActualTeam;
use App\Models\AuctionActionLog;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\BidIncrementService;
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
                // Show team selector for admin
                $allTeams = ActualTeam::forTournament($auction->tournament_id)->get();

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
        $maxBudget = $this->cap($purse['allocated'] ?: (float) ($auction->max_budget_per_team ?? 0));
        $remainingBudget = $this->cap($purse['remaining']);
        $maxBidAllowed = $this->cap($purse['max_bid_allowed']);

        // Get the initial state of the auction for the view.
        $auctionPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids' => fn ($query) => $query->latest('amount'),
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
                'bids' => $auctionPlayer->bids->map(function ($bid) {
                    return [
                        'id' => $bid->id,
                        'amount' => $bid->amount,
                        'team' => $bid->team ? [
                            'id' => $bid->team->id,
                            'name' => $bid->team->name,
                        ] : null,
                    ];
                })->toArray(),
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

        $newPrice = null;

        try {
            DB::transaction(function () use ($validated, $userTeam, $auction, &$newPrice) {
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

                if ($auction->bid_type === 'open') {
                    // Open bid: the server sets the amount from the increment ladder.
                    $bidAmount = $this->increments->nextBidAmount($auction, $current);

                    if ($bidAmount === null) {
                        throw new \Exception('Maximum bid reached. No further increments available.');
                    }
                } else {
                    // Closed bid: the team names its own amount, floored at the
                    // current price plus one increment.
                    if (! isset($validated['amount']) || $validated['amount'] <= 0) {
                        throw new \Exception('Bid amount is required for closed bid.');
                    }
                    $bidAmount = (float) $validated['amount'];

                    $minBid = $this->increments->nextBidAmount($auction, $current)
                        ?? (float) $auctionPlayer->base_price;

                    if ($bidAmount < $minBid) {
                        throw new \Exception('Bid must be at least ' . format_points($minBid) . ' (current price + increment).');
                    }
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

                // Auto-transition: open → closed (if threshold configured and not manually overridden)
                $freshAuction = Auction::find($auction->id);
                if ($freshAuction->hasAutoPhaseTransition()
                    && ! $freshAuction->mode_manually_overridden
                    && $freshAuction->bid_type === 'open'
                    && $bidAmount >= (float) $freshAuction->closed_bid_starts_at) {
                    $freshAuction->update(['bid_type' => 'closed']);
                }

                // Auto-transition to offline if price exceeds online limit
                $freshAuction = $freshAuction->fresh();
                if ($freshAuction->hasOnlineOfflineMode()
                    && ! $freshAuction->mode_manually_overridden
                    && $bidAmount > (float) $freshAuction->online_bid_limit_to) {
                    $freshAuction->update(['open_bid_mode' => 'offline']);
                }
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
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
            'reserve_amount' => $purse['reserve'],
            'slots_filled' => $purse['slots_filled'],
            'slots_required' => $purse['slots_required'],
            'slots_remaining' => $purse['slots_remaining'],
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
