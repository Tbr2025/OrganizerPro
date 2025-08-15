<?php

namespace App\Http\Controllers\Backend;

use App\Events\PlayerSoldEvent;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
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

        $messages = [
            'organization_id.required' => 'You must select an organization for the auction.',
            'tournament_id.required' => 'You must select a tournament for the auction.',
            'bid_rules.*.to.gt' => 'The "To" value in a bid rule must be greater than the "From" value.',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_id' => [Auth::user()->hasRole('Superadmin') ? 'required' : 'nullable', 'exists:organizations,id'],
            'tournament_id' => 'required|exists:tournaments,id',
            'status' => 'required|string|in:scheduled,running,completed',
            'max_budget_per_team' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'bid_rules' => 'required|array|min:1',
            'bid_rules.*.from' => 'required|numeric|min:0',
            'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
            'bid_rules.*.increment' => 'required|numeric|min:0',

            // Player pool data (optional at creation)
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'exists:players,id',
            'player_base_prices' => 'nullable|array',
            'player_base_prices.*' => 'required|numeric|min:0',
        ], $messages);

        if (!Auth::user()->hasRole('Superadmin')) {
            $validated['organization_id'] = Auth::user()->organization_id;
            if (!$validated['organization_id']) {
                return back()->with('error', 'You are not assigned to an organization and cannot create an auction.');
            }
        }

        DB::transaction(function () use ($validated) {

            // Create the auction
            $auction = Auction::create([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'bid_rules' => $validated['bid_rules'],
            ]);

            // Add players to the auction pool (if provided)
            $playerIdsInPool = $validated['player_ids'] ?? [];
            $basePrices = $validated['player_base_prices'] ?? [];

            foreach ($playerIdsInPool as $playerId) {
                AuctionPlayer::create([
                    'auction_id' => $auction->id,
                    'player_id' => $playerId,
                    'base_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'current_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'starting_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'organization_id' => $auction->organization_id,
                    'status' => 'waiting',
                ]);
            }
        });

        return redirect()->route('admin.auctions.index')->with('success', 'Auction configured and created successfully.');
    }


    public function show(Auction $auction)
    {
        $this->authorize('auction.view');

        $auction->load([
            'organization',
            'tournament',
            'auctionPlayers.player.playerType',
            'auctionPlayers.soldToTeam'
        ]);

        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->orderBy('name')
            ->get();

        // Decode bid_rules JSON from the DB
        $bidRules = is_string($auction->bid_rules)
            ? json_decode($auction->bid_rules, true)
            : $auction->bid_rules; // Already array if cast in model


        return view('backend.pages.auctions.show', [
            'auction'   => $auction,
            'teams'     => $teams,
            'bidRules'  => $bidRules
        ]);
    }


    public function fetchPlayers(Request $request, Auction $auction)
    {
        $this->authorize('auction.view');

        // Load auction relationships for player and team info
        $auction->load([
            'organization',
            'tournament',
            'auctionPlayers.player.playerType',
            'auctionPlayers.soldToTeam'
        ]);

        // Start query on auctionPlayers
        $query = $auction->auctionPlayers()->with([
            'player.playerType',
            'soldToTeam'
        ]);

        // --- Search & Filters ---
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('player', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($request->filled('player_type')) {
            $type = $request->input('player_type');
            $query->whereHas('player.playerType', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('team_id')) {
            $teamId = $request->input('team_id');
            $query->where('sold_to_team_id', $teamId);
        }

        // --- Ordering ---
        $query->orderByRaw("CASE WHEN status = 'on_auction' THEN 0 ELSE 1 END") // on_auction first
            ->orderBy('updated_at', 'desc');

        $players = $query->get()->map(function ($ap) {
            $player = $ap->player;
            $playerType = $player->playerType;
            $soldTeam = $ap->soldToTeam;

            return [
                'id' => $ap->id,
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'email' => $player->email,
                    'image_path' => $player->image_path,
                    'player_type' => $playerType ? $playerType->type : null,
                ],
                'status' => $ap->status,
                'base_price' => $ap->base_price,
                'current_price' => $ap->current_price,
                'final_price' => $ap->final_price ?? $ap->current_price,
                'sold_to_team' => $soldTeam ? [
                    'id' => $soldTeam->id,
                    'name' => $soldTeam->name,
                ] : null,
                'updated_at' => $ap->updated_at,
            ];
        });

        // Teams for filters
        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->orderBy('name')
            ->get();

        // Decode bid rules
        $bidRules = is_string($auction->bid_rules) ? json_decode($auction->bid_rules, true) : $auction->bid_rules;

        return response()->json([
            'players' => $players,
            'teams' => $teams,
            'bidRules' => $bidRules,
        ]);
    }


    public function addBid(Request $request)
    {
        $data = $request->validate([
            'auctionId' => 'required|integer|exists:auctions,id',
            'playerID'  => 'required|integer|exists:auction_players,id',
            'teamId'   => 'nullable|integer|exists:actual_teams,id' // optional team to log
        ]);

        $auction = Auction::findOrFail($data['auctionId']);
        $player  = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($data['playerID']);

        // Decode bid rules
        $rules = $auction->bid_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        $current = (int) $player->current_price;

        // Max bid check
        $maxTo = 0;
        foreach ($rules as $r) {
            $to = isset($r['to']) ? (int) $r['to'] : 0;
            if ($to > $maxTo) {
                $maxTo = $to;
            }
        }

        if ($maxTo > 0 && $current >= $maxTo) {
            return response()->json([
                'success'        => false,
                'message'        => 'Maximum bid reached.',
                'current_price'  => $current
            ], 400);
        }

        // Determine increment
        $increment = 0;
        foreach ($rules as $r) {
            $from = isset($r['from']) ? (int) $r['from'] : 0;
            $to   = isset($r['to']) ? (int) $r['to'] : PHP_INT_MAX;
            $inc  = isset($r['increment']) ? (int) $r['increment'] : 0;

            if ($current >= $from && $current < $to) {
                $increment = $inc;
                break;
            }
        }

        // New price
        $newPrice = $current + $increment;
        $player->current_price = $newPrice;
        $player->save();

        // Create auction bid record
        AuctionBid::create([
            'auction_id'        => $auction->id,
            'auction_player_id' => $player->id,
            'player_id'         => $player->player_id,
            'team_id'           => $data['teamId'] ?? null, // set from request or null
            'user_id'           => auth()->id(), // admin or user placing the bid
            'amount'            => $newPrice
        ]);

        return response()->json([
            'success'        => true,
            'current_price'  => $newPrice,
            'increment_used' => $increment
        ]);
    }



    /**
     * Decide increment based on bid rules JSON
     */
    protected function getBidIncrement($bidRulesJson, $currentPrice)
    {
        // Decode and ensure it's an array
        $rules = json_decode($bidRulesJson, true);

        if (!is_array($rules) || empty($rules)) {
            // fallback default increment
            return 1000;
        }

        foreach ($rules as $rule) {
            if ($currentPrice >= $rule['min'] && $currentPrice <= $rule['max']) {
                return $rule['increment'];
            }
        }

        return 1000; // default if no match
    }


    public function decreaseBid(Request $request)
    {
        $data = $request->validate([
            'auctionId' => 'required|integer|exists:auctions,id',
            'playerID'  => 'required|integer|exists:auction_players,id',
        ]);

        $auction = Auction::findOrFail($data['auctionId']);
        $player  = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($data['playerID']);

        // Decode bid rules
        $rules = $auction->bid_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        $current = (int) $player->current_price;

        // Determine decrement using the same rules
        $decrement = 0;
        foreach ($rules as $r) {
            $from = isset($r['from']) ? (int) $r['from'] : 0;
            $to   = isset($r['to']) ? (int) $r['to'] : PHP_INT_MAX;
            $inc  = isset($r['increment']) ? (int) $r['increment'] : 0;

            // For decrement, find the rule where current price falls within
            if ($current > $from && $current <= $to) {
                $decrement = $inc;
                break;
            }
        }

        // New price: cannot go below base_price
        $newPrice = max($player->base_price, $current - $decrement);
        $player->current_price = $newPrice;
        $player->save();

        // Optional: record a "negative bid" or decrement action if needed
        // AuctionBid::create([...]);

        return response()->json([
            'success'        => true,
            'current_price'  => $newPrice,
            'decrement_used' => $decrement
        ]);
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
                $query->where('player_mode', '!=', 'retained')->orWhereNull('player_mode');
            })
            ->whereNotIn('id', $auctionPlayerIds)
            ->whereHas('user', function ($query) use ($auction) {
                $query->where('organization_id', $auction->organization_id);
            })
            ->orderBy('name')
            ->get();

        return view('backend.pages.auctions.edit', compact('auction', 'organizations', 'tournaments', 'availablePlayers'));
    }





    public function closeBid(Request $request)
    {
        $this->authorize('auction.edit');

        $auctionPlayer = AuctionPlayer::findOrFail($request->playerID);
        $auctionPlayer->status = 'closed';
        $auctionPlayer->save();

        // Optional: broadcast status change

        return response()->json([
            'success' => true,
            'status' => 'closed'
        ]);
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
            'status' => 'required|string|in:scheduled,running,completed',
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
                    'status' => 'scheduled', // Default status when added
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
                    'base_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'starting_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'organization_id' => $auction->organization_id,
                    'status' => 'waiting',
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


    public function clearPool(Auction $auction)
    {
        $this->authorize('auctions.edit'); // Protect with the same permission as editing

        // Use the relationship to delete all associated auction players.
        $auction->auctionPlayers()->delete();

        return back()->with('success', 'The entire player pool has been cleared.');
    }

    public function removePlayer(AuctionPlayer $auctionPlayer)
    {
        $this->authorize('auctions.edit');

        // The route model binding finds the correct AuctionPlayer record for us.
        // We just need to delete it.
        $auctionPlayer->delete();

        // Return a JSON response for the frontend.
        return response()->json(['success' => true, 'message' => 'Player removed from pool.']);
    }



    public function assignPlayer(Request $request)
    {
        $this->authorize('auction.edit');

        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'team_id' => 'required|exists:actual_teams,id',
        ]);

        $auctionPlayer = AuctionPlayer::findOrFail($validated['auction_player_id']);
        $team = ActualTeam::findOrFail($validated['team_id']);
        $auction = $auctionPlayer->auction;

        DB::transaction(function () use ($auctionPlayer, $team, $auction) {
            $salePrice = $auctionPlayer->current_price ?? $auctionPlayer->base_price;

            // Log the transaction
            AuctionBid::create([
                'auction_id' => $auction->id,
                'auction_player_id' => $auctionPlayer->id,
                'player_id' => $auctionPlayer->player_id,
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'amount' => $salePrice,
            ]);

            // Mark player as sold
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => $salePrice,
                'current_price' => $salePrice,
                'current_bid_team_id' => $team->id,
            ]);

            // Update player status
            // 3. Update the main player's system status
            $auctionPlayer->player()->update([
                'player_mode'     => 'sold',
                'actual_team_id'  => $team->id
            ]);


            // Add to team roster
            $team->users()->syncWithoutDetaching([
                $auctionPlayer->player->user_id => ['role' => 'Player']
            ]);

            // Sync Spatie role
            $user = $auctionPlayer->player->user;
            if ($user && !$user->hasAnyRole(['Superadmin', 'Admin'])) {
                $user->syncRoles(['Player']);
            }

            // Broadcast
            broadcast(new PlayerSoldEvent($auctionPlayer, $team));
        });

        return back()->with('success', 'Player has been successfully assigned, sold, and added to the team.');
    }
}
