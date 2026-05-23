<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionBid;
use App\Models\ActualTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuctionBiddingController extends Controller
{
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

                if (!$userTeam) {
                    abort(404, 'Team not found in this tournament.');
                }

                $isPreviewMode = true;
            } else {
                // Show team selector for admin
                $allTeams = ActualTeam::forTournament($auction->tournament_id)->get();

                return view('backend.pages.auction.bidding-team-selector', compact(
                    'auction',
                    'allTeams'
                ));
            }
        } else {
            // Regular team manager - find their team
            $userTeam = $user->actualTeams()
                ->forTournament($auction->tournament_id)
                ->first();

            // Security: Abort if the user is not on a participating team.
            if (!$userTeam) {
                abort(403, 'Your team is not a participant in this tournament\'s auction.');
            }
        }

        // Calculate the remaining budget.
        $spentSoFar = (float) $auction->auctionPlayers()
            ->where('sold_to_team_id', $userTeam->id)
            ->sum('final_price');

        // Calculate remaining budget as separate variable
        $maxBudget = (float) ($auction->max_budget_per_team ?? 100000000); // Default 10 Cr if not set
        $remainingBudget = (int) ($maxBudget - $spentSoFar);

        // Get the initial state of the auction for the view.
        $auctionPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids' => fn($query) => $query->latest('amount'),
                'bids.team',
                'bids.user',
                'currentBidTeam'
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
                'bids' => $auctionPlayer->bids->map(function($bid) {
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

        // Get all teams with their budgets
        $allTeams = ActualTeam::forTournament($auction->tournament_id)
            ->get()
            ->map(function ($team) use ($auction) {
                $spent = $auction->auctionPlayers()
                    ->where('sold_to_team_id', $team->id)
                    ->sum('final_price');
                $maxBudget = (float) ($auction->max_budget_per_team ?? 100000000);
                $team->spent = (int) $spent;
                $team->remaining = (int) ($maxBudget - $spent);
                $team->players_count = $auction->auctionPlayers()
                    ->where('sold_to_team_id', $team->id)
                    ->count();
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
            'allTeams',
            'soldPlayers',
            'myBid'
        ));
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

        $userTeam = Auth::user()->actualTeams()->first();
        if (!$userTeam) {
            return response()->json(['error' => 'You are not assigned to a team.'], 403);
        }

        // Reject bids when auction is in offline mode (applies to any bid_type)
        $freshAuction = $auction->fresh();
        if ($freshAuction->open_bid_mode === 'offline') {
            return response()->json([
                'error' => 'Bidding is currently in offline mode. The organizer is handling bids manually.'
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

                // For open bid: calculate next price from bid rules
                if ($auction->bid_type === 'open') {
                    $current = (float) $auctionPlayer->current_price;
                    $rules = $auction->bid_rules;
                    if (is_string($rules)) $rules = json_decode($rules, true);
                    if (!is_array($rules)) $rules = [];

                    $increment = 0;
                    foreach ($rules as $r) {
                        $from = isset($r['from']) ? (float) $r['from'] : 0;
                        $to = isset($r['to']) ? (float) $r['to'] : PHP_FLOAT_MAX;
                        $inc = isset($r['increment']) ? (float) $r['increment'] : 0;
                        if ($current >= $from && $current <= $to) {
                            $increment = $inc;
                            break;
                        }
                    }

                    // Fallback: find next applicable rule
                    if ($increment == 0) {
                        foreach ($rules as $r) {
                            $from = isset($r['from']) ? (float) $r['from'] : 0;
                            $inc = isset($r['increment']) ? (float) $r['increment'] : 0;
                            if ($current < $from) {
                                $increment = $inc;
                                break;
                            }
                        }
                    }

                    if ($increment == 0) {
                        throw new \Exception('Maximum bid reached. No further increments available.');
                    }

                    $bidAmount = $current + $increment;
                } else {
                    // Closed bid: use the amount provided
                    if (!isset($validated['amount']) || $validated['amount'] <= 0) {
                        throw new \Exception('Bid amount is required for closed bid.');
                    }
                    $bidAmount = (float) $validated['amount'];

                    // Calculate minimum bid based on bid rules (same increment logic as open bid)
                    $current = (float) $auctionPlayer->current_price;
                    $rules = $auction->bid_rules;
                    if (is_string($rules)) $rules = json_decode($rules, true);
                    if (!is_array($rules)) $rules = [];

                    $closedIncrement = 0;
                    foreach ($rules as $r) {
                        $from = isset($r['from']) ? (float) $r['from'] : 0;
                        $to = isset($r['to']) ? (float) $r['to'] : PHP_FLOAT_MAX;
                        $inc = isset($r['increment']) ? (float) $r['increment'] : 0;
                        if ($current >= $from && $current <= $to) {
                            $closedIncrement = $inc;
                            break;
                        }
                    }
                    if ($closedIncrement == 0) {
                        foreach ($rules as $r) {
                            $from = isset($r['from']) ? (float) $r['from'] : 0;
                            $inc = isset($r['increment']) ? (float) $r['increment'] : 0;
                            if ($current < $from) {
                                $closedIncrement = $inc;
                                break;
                            }
                        }
                    }

                    $minBid = $closedIncrement > 0 ? $current + $closedIncrement : $auctionPlayer->base_price;

                    if ($bidAmount < $minBid) {
                        throw new \Exception('Bid must be at least ' . number_format($minBid) . ' (current price + increment).');
                    }
                }

                // Budget validation
                $spentBudget = AuctionPlayer::where('auction_id', $auction->id)
                    ->where('sold_to_team_id', $userTeam->id)
                    ->where('status', 'sold')
                    ->sum('final_price');
                $remainingBudget = $auction->max_budget_per_team - $spentBudget;

                if ($bidAmount > $remainingBudget) {
                    throw new \Exception('Bid exceeds your remaining budget of ' . number_format($remainingBudget) . '.');
                }

                // Create a new bid record
                AuctionBid::create([
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

                $newPrice = $bidAmount;

                // Auto-transition: open → closed (if threshold configured and not manually overridden)
                $freshAuction = Auction::find($auction->id);
                if ($freshAuction->hasAutoPhaseTransition()
                    && !$freshAuction->mode_manually_overridden
                    && $freshAuction->bid_type === 'open'
                    && $bidAmount >= (float) $freshAuction->closed_bid_starts_at) {
                    $freshAuction->update(['bid_type' => 'closed']);
                }

                // Auto-transition to offline if price exceeds online limit
                $freshAuction = $freshAuction->fresh();
                if ($freshAuction->hasOnlineOfflineMode()
                    && !$freshAuction->mode_manually_overridden
                    && $bidAmount > (float) $freshAuction->online_bid_limit_to) {
                    $freshAuction->update(['open_bid_mode' => 'offline']);
                }
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => 'Bid placed successfully.',
            'new_price' => $newPrice,
            'team_name' => $userTeam->name,
        ]);
    }
}
