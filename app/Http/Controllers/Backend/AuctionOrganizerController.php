<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\ActualTeam;
use App\Models\Player;
use App\Events\AuctionStatusUpdate;
use App\Events\PlayerOnBid;
use App\Events\PlayerSoldEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuctionOrganizerController extends Controller
{
    // Note: It's assumed you will protect these routes with middleware 
    // to ensure only users with the 'Organizer' role can access them.

    /**
     * Display the Organizer's control panel view.
     */
    public function showPanel(Auction $auction)
    {
        // Fetch available players (waiting status)
        $availablePlayers = $auction->auctionPlayers()
            ->where('status', 'waiting')
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        // Fetch current player on auction (if any)
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids.team',
                'bids.user',
                'soldToTeam'
            ])
            ->first();

        // Fetch sold players
        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->get();

        // Fetch teams with their budget calculations
        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->withCount(['auctionPlayers as players_bought' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }])
            ->withSum(['auctionPlayers as total_spent' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }], 'final_price')
            ->get()
            ->map(function ($team) use ($auction) {
                $team->remaining_budget = $auction->max_budget_per_team - ($team->total_spent ?? 0);
                return $team;
            });

        // Stats
        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $auction->auctionPlayers()->where('status', 'sold')->count(),
            'unsold_count' => $auction->auctionPlayers()->where('status', 'unsold')->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        return view('backend.pages.auction.organizer-panel', compact(
            'auction',
            'availablePlayers',
            'currentPlayer',
            'soldPlayers',
            'teams',
            'stats'
        ));
    }

    /**
     * Return full auction state as JSON for polling.
     */
    public function pollState(Auction $auction)
    {
        $availablePlayers = $auction->auctionPlayers()
            ->where('status', 'waiting')
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids.team',
                'bids.user',
                'soldToTeam'
            ])
            ->first();

        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->withCount(['auctionPlayers as players_bought' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }])
            ->withSum(['auctionPlayers as total_spent' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }], 'final_price')
            ->get()
            ->map(function ($team) use ($auction) {
                $team->remaining_budget = $auction->max_budget_per_team - ($team->total_spent ?? 0);
                return $team;
            });

        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $soldPlayers->count(),
            'unsold_count' => $auction->auctionPlayers()->where('status', 'unsold')->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        $freshAuction = $auction->fresh();

        return response()->json([
            'auction_status' => $freshAuction->status,
            'available_players' => $availablePlayers,
            'current_player' => $currentPlayer,
            'sold_players' => $soldPlayers,
            'teams' => $teams,
            'stats' => $stats,
            'open_bid_mode' => $freshAuction->open_bid_mode,
            'mode_manually_overridden' => (bool) $freshAuction->mode_manually_overridden,
            'online_bid_limit_from' => $freshAuction->online_bid_limit_from,
            'online_bid_limit_to' => $freshAuction->online_bid_limit_to,
            'bid_type' => $freshAuction->bid_type,
            'closed_bid_starts_at' => $freshAuction->closed_bid_starts_at,
        ]);
    }

    /**
     * Start the auction.
     */
    public function startAuction(Auction $auction)
    {
        $auction->update(['status' => 'running']);
        broadcast(new AuctionStatusUpdate($auction->id, 'running'));
        return response()->json(['message' => 'Auction has been started.']);
    }

    /**
     * End the auction.
     */
    public function endAuction(Auction $auction)
    {
        $auction->update(['status' => 'completed']);
        broadcast(new AuctionStatusUpdate($auction->id, 'completed'));
        return response()->json(['message' => 'Auction has been completed.']);
    }

    /**
     * Restart a completed auction — sets status back to running.
     */
    public function restartAuction(Auction $auction)
    {
        if ($auction->status !== 'completed') {
            return response()->json(['message' => 'Only completed auctions can be restarted.'], 422);
        }

        $auction->update(['status' => 'running']);
        broadcast(new AuctionStatusUpdate($auction->id, 'running'));
        return response()->json(['success' => true, 'message' => 'Auction has been restarted.']);
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
            'auction_player_id' => 'required|exists:auction_players,id'
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->where('status', 'waiting') // ✅ only select waiting players
            ->first();

        // If no player found (either doesn't exist or not waiting)
        if (!$auctionPlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Player not available to put on bid. Only players with status "waiting" can be selected.'
            ], 400);
        }

        // Check if any other player is live
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Some player is already live in the auction! Please close that bid before starting with the next player!'
            ], 400);
        }

        // Set this player live
        $auctionPlayer->update([
            'status' => 'on_auction',
            'current_price' => $auctionPlayer->base_price,
            'current_bid_team_id' => null,
        ]);

        // Reset phase for new player
        if ($auction->hasAutoPhaseTransition()) {
            $auction->update([
                'bid_type' => 'open',
                'open_bid_mode' => 'online',
                'mode_manually_overridden' => false,
            ]);
        } elseif ($auction->bid_type === 'open') {
            $auction->update([
                'open_bid_mode' => 'online',
                'mode_manually_overridden' => false,
            ]);
        }

        // Eager-load relationships for broadcast
        $playerDataForBroadcast = $auctionPlayer->fresh([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'bids.team',
            'bids.user'
        ]);

        broadcast(new PlayerOnBid($playerDataForBroadcast));

        return response()->json([
            'success' => true,
            'message' => 'Player is now live for bidding.'
        ]);
    }


    /**
     * Mark the current player as "Sold" to the highest bidder.
     */
    public function sellPlayer(Request $request, Auction $auction)
    {
        // dd($sellPlayer);
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)->firstOrFail();

        // Find the winning bid
        $winningBid = $auctionPlayer->bids()->latest('amount')->first();

        if ($winningBid) {
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $winningBid->team_id,
                'final_price' => $winningBid->amount,
            ]);

            // Update the main player's mode to 'retained'
            Player::where('id', $auctionPlayer->player_id)->update(['player_mode' => 'retained']);

            broadcast(new PlayerSoldEvent($auctionPlayer, $winningBid->team));
        } else {
            // If no bids, mark as unsold
            $this->passPlayer($request, $auction);
        }

        return response()->json(['message' => 'Player status updated to SOLD.']);
    }

    /**
     * Mark the current player as "Unsold/Passed".
     */
    public function passPlayer(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)->firstOrFail();

        $auctionPlayer->update(['status' => 'unsold']);

        // Still broadcast the "sold" event so the UI can update, but without a winning team
        broadcast(new PlayerSoldEvent($auctionPlayer, null));

        return response()->json(['message' => 'Player has been passed.']);
    }



    public function togglePause(Auction $auction)
    {
        // 1. Determine the new status
        // If the current status is 'running', the new status will be 'paused'.
        // Otherwise, the new status will be 'running'.
        $newStatus = ($auction->status === 'running') ? 'paused' : 'running';

        // 2. Security/Logic Check: Only allow toggling if the auction is currently running or paused.
        if (!in_array($auction->status, ['running', 'paused'])) {
            return response()->json(['message' => 'Auction cannot be paused or resumed at this time.'], 422); // Unprocessable Entity
        }

        // 3. Update the auction's status in the database
        $auction->update(['status' => $newStatus]);

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
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'team_id' => 'required|exists:actual_teams,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        $team = ActualTeam::findOrFail($validated['team_id']);

        // Calculate total spent budget for the team
        $spentBudget = AuctionPlayer::where('auction_id', $auction->id)
            ->where('sold_to_team_id', $team->id)
            ->where('status', 'sold')
            ->sum('final_price');

        $availableBalance = $auction->max_budget_per_team - $spentBudget;

        if ($validated['amount'] > $availableBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient team balance. Available: ' . number_format($availableBalance)
            ], 400);
        }

        DB::transaction(function () use ($auctionPlayer, $team, $validated, $auction) {
            // Mark player as sold
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => $validated['amount'],
                'current_price' => $validated['amount'],
                'current_bid_team_id' => $team->id,
            ]);

            // Create an audit bid record for offline sale
            AuctionBid::create([
                'auction_id' => $auction->id,
                'auction_player_id' => $auctionPlayer->id,
                'player_id' => $auctionPlayer->player_id,
                'team_id' => $team->id,
                'user_id' => auth()->id(),
                'amount' => $validated['amount'],
                'bid_source' => 'offline',
            ]);

            // Update the main player's mode (consistent with existing sellPlayer)
            Player::where('id', $auctionPlayer->player_id)->update(['player_mode' => 'retained']);
        });

        broadcast(new PlayerSoldEvent($auctionPlayer->fresh(), $team));

        return response()->json([
            'success' => true,
            'message' => 'Player sold to ' . $team->name . ' for ' . number_format($validated['amount']),
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
            'mode_manually_overridden' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Switched to ' . strtoupper($validated['bid_type']) . ' bid.',
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
            ->with(['team', 'user']);

        if ($auctionPlayerId) {
            $query->where('auction_player_id', $auctionPlayerId);
        }

        $bids = $query->orderByDesc('amount')->get()->map(function ($bid) {
            return [
                'id' => $bid->id,
                'team_id' => $bid->team_id,
                'team_name' => $bid->team->name ?? 'Unknown',
                'team_logo' => $bid->team->logo_path ?? null,
                'amount' => $bid->amount,
                'user_name' => $bid->user->name ?? 'Unknown',
                'created_at' => $bid->created_at->toISOString(),
            ];
        });

        return response()->json(['bids' => $bids]);
    }
}
