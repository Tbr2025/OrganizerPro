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
        $user = Auth::user();

        // Find the user's team that is part of this specific tournament.
        $userTeam = $user->actualTeams()->where('tournament_id', $auction->tournament_id)->first();

        // **CRITICAL FIX**: If the user is not on a team for this tournament,
        // they cannot access the page. Abort with a clear error message.
        if (!$userTeam) {
            abort(403, 'Your team is not a participant in this tournament\'s auction.');
        }

        // Now that we have the team, let's calculate their budget.
        // The max budget comes from the main auction settings.
        $maxBudget = $auction->max_budget_per_team;

        // Calculate how much the team has already spent IN THIS AUCTION.
        $spentSoFar = AuctionPlayer::where('auction_id', $auction->id)
            ->where('sold_to_team_id', $userTeam->id)
            ->sum('final_price');

        // The remaining budget is what's left.
        $remainingBudget = $maxBudget - $spentSoFar;

        // We will add this calculated value to the team object before sending it to the view.
        // This keeps the view's logic simple.
        $userTeam->remaining_budget = $remainingBudget;

        // Get the current live player to show on page load, if any.
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'live')
            ->with(['player', 'bids.team', 'bids.user'])
            ->first();

        // Now we pass all the confirmed data to the view.
        return view('backend.pages.auction.bidding-page', compact('auction', 'userTeam', 'currentPlayer'));
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

                if ($auctionPlayer->status !== 'live') {
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
