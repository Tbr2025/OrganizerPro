<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionBid;
use App\Models\ActualTeam;
use App\Events\NewBidPlaced;
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
                    ->where('tournament_id', $auction->tournament_id)
                    ->first();

                if (!$userTeam) {
                    abort(404, 'Team not found in this tournament.');
                }

                $isPreviewMode = true;
            } else {
                // Show team selector for admin
                $allTeams = ActualTeam::where('tournament_id', $auction->tournament_id)->get();

                return view('backend.pages.auction.bidding-team-selector', compact(
                    'auction',
                    'allTeams'
                ));
            }
        } else {
            // Regular team manager - find their team
            $userTeam = $user->actualTeams()
                ->where('tournament_id', $auction->tournament_id)
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
        $allTeams = ActualTeam::where('tournament_id', $auction->tournament_id)
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

        // Return the view with all necessary data.
        return view('backend.pages.auction.bidding-page', compact(
            'auction',
            'userTeam',
            'currentPlayer',
            'isPreviewMode',
            'remainingBudget',
            'allTeams',
            'soldPlayers'
        ));
    }


    /**
     * Handle a bid submission from a Team Manager.
     */
    public function placeBid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $userTeam = Auth::user()->actualTeams()->first();
        if (!$userTeam) {
            return response()->json(['error' => 'You are not assigned to a team.'], 403);
        }

        // Use a database transaction for safety
        try {
            DB::transaction(function () use ($validated, $userTeam, $auction) {
                $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
                    ->where('auction_id', $auction->id)
                    ->lockForUpdate() // Lock the row to prevent race conditions
                    ->firstOrFail();

                if ($auctionPlayer->status !== 'on_auction') {
                    throw new \Exception('Bidding is not active for this player.');
                }

                if ($validated['amount'] <= $auctionPlayer->current_price) {
                    throw new \Exception('Your bid must be higher than the current bid.');
                }

                // Add your budget and increment validation logic here...
                // ...

                $auctionPlayer->update([
                    'current_price' => $validated['amount'],
                    'current_bid_team_id' => $userTeam->id,
                ]);

                $newBid = AuctionBid::create([
                    'auction_id' => $auction->id,
                    'auction_player_id' => $auctionPlayer->id,
                    'player_id' => $auctionPlayer->player_id,
                    'team_id' => $userTeam->id,
                    'user_id' => auth()->id(),
                    'amount' => $validated['amount'],
                ]);

                // Broadcast the new bid to everyone else
                broadcast(new NewBidPlaced($newBid))->toOthers();
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => 'Bid placed successfully.']);
    }
}
