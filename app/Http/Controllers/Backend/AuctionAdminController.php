<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuctionAdminController extends Controller
{
    /**
     * Display a list of all auctions.
     */
    public function index()
    {
        $query = Auction::with('tournament', 'organization');
        if (!Auth::user()->hasRole('Superadmin')) {
            $query->where('organization_id', Auth::user()->organization_id);
        }
        $auctions = $query->latest()->paginate(15);
        return view('backend.pages.auctions.index', compact('auctions'));
    }

    /**
     * Show the form for creating a new auction.
     */
    public function create()
    {
        $organizations = Organization::orderBy('name')->get();
        $tournaments = Tournament::orderBy('name')->get();
        return view('backend.pages.auctions.create', compact('organizations', 'tournaments'));
    }

    public function store(Request $request)
    {
        $this->authorize('auction.create');

        // 1. Define custom messages for better user feedback
        $messages = [
            'organization_id.required' => 'You must select an organization for the auction.',
            'tournament_id.required' => 'You must select a tournament for the auction.',
            'bid_rules.*.to.gt' => 'The "To" value in a bid rule must be greater than the "From" value.',
        ];

        // 2. Validate the request with the custom messages
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_id' => [Auth::user()->hasRole('Superadmin') ? 'required' : 'nullable', 'exists:organizations,id'],
            'tournament_id' => 'required|exists:tournaments,id',
            'status' => 'required|string|in:pending,live,completed',
            'max_budget_per_team' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'bid_rules' => 'required|array|min:1',
            'bid_rules.*.from' => 'required|numeric|min:0',
            'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
            'bid_rules.*.increment' => 'required|numeric|min:0',
        ], $messages); // <-- Pass the custom messages array here

        // 3. Automatically set organization ID for non-Superadmins
        if (!Auth::user()->hasRole('Superadmin')) {
            $validated['organization_id'] = Auth::user()->organization_id;

            // Safety check to ensure the user has an organization ID
            if (!$validated['organization_id']) {
                return back()->with('error', 'You are not assigned to an organization and cannot create an auction.');
            }
        }

        // 4. Create the auction
        Auction::create($validated);

        // 5. Redirect with success
        return redirect()->route('admin.auctions.index')->with('success', 'Auction configured and created successfully.');
    }

    public function show(Auction $auction)
    {
        // Eager-load all necessary data for the dashboard view
        $auction->load(['tournament', 'organization', 'auctionPlayers.player.playerType']);
        return view('backend.pages.auctions.show', compact('auction'));
    }

    public function edit(Auction $auction)
    {
        $organizations = Organization::orderBy('name')->get();
        $tournaments = Tournament::orderBy('name')->get();

        // Load the players currently in the auction to get their IDs
        $auction->load('auctionPlayers.player');
        $auctionPlayerIds = $auction->auctionPlayers->pluck('player.id')->toArray();

        // Find players who are available (Verified, not Retained, and not already in this auction)
        $availablePlayers = Player::whereNotNull('welcome_email_sent_at')
            ->where(function ($query) {
                $query->where('player_status', '!=', 'Retained')->orWhereNull('player_status');
            })
            ->whereNotIn('id', $auctionPlayerIds)
            ->whereHas('user', function ($query) use ($auction) {
                $query->where('organization_id', $auction->organization_id);
            })
            ->orderBy('name')
            ->get();

        return view('backend.pages.auctions.edit', compact('auction', 'organizations', 'tournaments', 'availablePlayers'));
    }
   






public function update(Request $request, Auction $auction)
{
    // 1. Authorize the action
    $this->authorize('auction.edit'); // Assuming a permission

    // 2. Validate all the data from your multi-step form
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'organization_id' => 'required|exists:organizations,id',
        'tournament_id' => 'required|exists:tournaments,id',
        'status' => 'required|string|in:pending,live,completed',
        'max_budget_per_team' => 'required|numeric|min:0',
        'base_price' => 'required|numeric|min:0',
        'bid_rules' => 'required|array|min:1',
        'bid_rules.*.from' => 'required|numeric|min:0',
        'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
        'bid_rules.*.increment' => 'required|numeric|min:0',
        
        // Player pool data
        'player_ids' => 'nullable|array',
        'player_ids.*' => 'exists:players,id', // Ensure all submitted IDs are valid players
        'player_base_prices' => 'nullable|array',
        'player_base_prices.*' => 'required|numeric|min:0',
    ]);

    // Use a database transaction to ensure data integrity.
    // If any step fails, all changes will be rolled back.
    DB::transaction(function () use ($validated, $auction, $request) {

        // 3. Update the main auction details (Steps 1, 2, 3)
        $auction->update([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'tournament_id' => $validated['tournament_id'],
            'status' => $validated['status'],
            'max_budget_per_team' => $validated['max_budget_per_team'],
            'base_price' => $validated['base_price'],
            'bid_rules' => $validated['bid_rules'],
        ]);

        // 4. Synchronize the Player Pool (Step 4)
        $playerIdsInPool = $validated['player_ids'] ?? [];
        $basePrices = $validated['player_base_prices'] ?? [];

        $syncData = [];
        foreach ($playerIdsInPool as $playerId) {
            // Prepare the data for the auction_players pivot table
            $syncData[$playerId] = [
                'base_price' => $basePrices[$playerId] ?? $auction->base_price, // Use specific price or default
                'organization_id' => $auction->organization_id, // Carry over the org ID
                // Other default values when a player is first added
                'status' => 'pending',
                'current_price' => $basePrices[$playerId] ?? $auction->base_price,
            ];
        }

        // The sync() method is perfect. It adds new players, removes old ones,
        // and because we are not using it to update, it won't touch existing records that stay.
        // However, a simple sync won't update base prices. So we do it manually for more control.
        
        $existingPlayerIds = $auction->auctionPlayers->pluck('player_id')->toArray();
        $playersToAdd = array_diff($playerIdsInPool, $existingPlayerIds);
        $playersToRemove = array_diff($existingPlayerIds, $playerIdsInPool);

        // Remove players who were taken out of the pool
        if (!empty($playersToRemove)) {
            AuctionPlayer::where('auction_id', $auction->id)->whereIn('player_id', $playersToRemove)->delete();
        }

        // Add new players to the pool
        foreach ($playersToAdd as $playerId) {
            AuctionPlayer::create([
                'auction_id' => $auction->id,
                'player_id' => $playerId,
                'base_price' => $basePrices[$playerId] ?? $auction->base_price,
                'current_price' => $basePrices[$playerId] ?? $auction->base_price,
                'organization_id' => $auction->organization_id,
                'status' => 'pending',
            ]);
        }
        
        // Update base prices for players who remained in the pool
        $playersToUpdate = array_intersect($playerIdsInPool, $existingPlayerIds);
        foreach ($playersToUpdate as $playerId) {
            if (isset($basePrices[$playerId])) {
                AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $playerId)
                    ->update(['base_price' => $basePrices[$playerId], 'current_price' => $basePrices[$playerId]]);
            }
        }
    });

    // 5. Redirect with a success message
    return redirect()->route('admin.auctions.index')->with('success', 'Auction and player pool updated successfully.');
}

    /**
     * Remove the specified auction from storage.
     */
    public function destroy(Auction $auction)
    {
        $auction->delete();
        return redirect()->route('admin.auctions.index')->with('success', 'Auction deleted successfully.');
    }
}
