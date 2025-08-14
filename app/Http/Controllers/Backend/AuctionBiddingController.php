<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionBid;
use App\Events\NewBidPlaced;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuctionBiddingController extends Controller
{
    /**
     * Display the Team Manager's bidding page.
     */
    public function showBiddingPage(Auction $auction)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. **REFINED**: Find the user's team more efficiently.
        // We assume a Team Manager is only on one team per tournament.
        $userTeam = $user->actualTeams()
            ->where('tournament_id', $auction->tournament_id)
            ->first();

        // 2. Security: Abort if the user is not on a participating team.
        if (!$userTeam) {
            abort(403, 'Your team is not a participant in this tournament\'s auction.');
        }

        // 3. **REFINED**: Calculate the remaining budget.
        // Use the relationship to make the query more readable.
        $spentSoFar = $auction->auctionPlayers()
            ->where('sold_to_team_id', $userTeam->id)
            ->sum('final_price');

        // Add the calculated remaining budget as a property to the team object.
        $userTeam->remaining_budget = $auction->max_budget_per_team - $spentSoFar;

        // 4. **REFINED**: Get the initial state of the auction for the view.
        // Eager-load all necessary nested relationships in one go.
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids' => fn($query) => $query->latest('amount'), // Get bids in order
                'bids.team',
                'bids.user',
                'currentBidTeam'
            ])
            ->first();

        // 5. Return the view with all necessary data.
        return view('backend.pages.auction.bidding-page', compact(
            'auction',
            'userTeam',
            'currentPlayer'
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
