<?php

namespace App\Http\Controllers\Backend;

use App\Events\PlayerOnBidEvent;
use App\Events\PlayerSoldEvent;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            'teamId'    => 'nullable|integer|exists:actual_teams,id'
        ]);

        $auction = Auction::findOrFail($data['auctionId']);
        $player  = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($data['playerID']);

        // Decode bid rules
        $rules = $auction->bid_rules;
        if (is_string($rules)) $rules = json_decode($rules, true);
        if (!is_array($rules)) $rules = [];

        $current = (float) $player->current_price;
        $increment = 0;

        foreach ($rules as $r) {
            $from = isset($r['from']) ? (float) $r['from'] : 0;
            $to   = isset($r['to']) ? (float) $r['to'] : PHP_FLOAT_MAX;
            $inc  = isset($r['increment']) ? (float) $r['increment'] : 0;

            if ($current >= $from && $current <= $to) {
                $increment = $inc;
                break;
            }
        }

        if ($increment == 0) {
            foreach ($rules as $r) {
                $from = isset($r['from']) ? (float) $r['from'] : 0;
                $inc  = isset($r['increment']) ? (float) $r['increment'] : 0;
                if ($current < $from) {
                    $increment = $inc;
                    break;
                }
            }
        }

        if ($increment == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum bid reached.',
                'current_price' => $current
            ], 400);
        }

        $newPrice = $current + $increment;
        $player->current_price = $newPrice;
        $player->final_price = $newPrice;
        $player->save();

        // Create auction bid record
        AuctionBid::updateOrCreate(
            [
                'auction_id'        => $auction->id,
                'auction_player_id' => $player->id,
                'team_id'           => $data['teamId'] ?? null,
                'user_id'           => auth()->id(),
            ],
            [
                'player_id'         => $player->player_id,
                'amount'            => $newPrice,
            ]
        );


        // Load relationships for frontend
        $player->load([
            'player.player_type',
            'player.batting_profile',
            'player.bowling_profile',
        ]);

        $team = ActualTeam::find($data['teamId'] ?? null);

        broadcast(new PlayerOnBidEvent($player, $team))->toOthers();

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


    // public function decreaseBid(Request $request)
    // {
    //     $data = $request->validate([
    //         'auctionId' => 'required|integer|exists:auctions,id',
    //         'playerID'  => 'required|integer|exists:auction_players,id',
    //     ]);

    //     $auction = Auction::findOrFail($data['auctionId']);
    //     $player  = AuctionPlayer::where('auction_id', $auction->id)
    //         ->findOrFail($data['playerID']);

    //     // Decode bid rules
    //     $rules = $auction->bid_rules;
    //     if (is_string($rules)) {
    //         $rules = json_decode($rules, true);
    //     }
    //     if (!is_array($rules)) {
    //         $rules = [];
    //     }

    //     $current = (int) $player->current_price;

    //     // Determine decrement using the same rules
    //     $decrement = 0;
    //     foreach ($rules as $r) {
    //         $from = isset($r['from']) ? (int) $r['from'] : 0;
    //         $to   = isset($r['to']) ? (int) $r['to'] : PHP_INT_MAX;
    //         $inc  = isset($r['increment']) ? (int) $r['increment'] : 0;

    //         // For decrement, find the rule where current price falls within
    //         if ($current > $from && $current <= $to) {
    //             $decrement = $inc;
    //             break;
    //         }
    //     }

    //     // New price: cannot go below base_price
    //     $newPrice = max($player->base_price, $current - $decrement);
    //     $player->current_price = $newPrice;
    //     $player->final_price = $newPrice;

    //     $player->save();

    //     // Optional: record a "negative bid" or decrement action if needed
    //     // AuctionBid::create([...]);

    //     return response()->json([
    //         'success'        => true,
    //         'current_price'  => $newPrice,
    //         'decrement_used' => $decrement
    //     ]);
    // }

    public function decreaseBid(Request $request)
    {
        $data = $request->validate([
            'auctionId' => 'required|integer|exists:auctions,id',
            'playerID'  => 'required|integer|exists:auction_players,id',
            'teamId'    => 'nullable|integer|exists:actual_teams,id'
        ]);

        $auction = Auction::findOrFail($data['auctionId']);
        $player  = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($data['playerID']);

        // Decode bid rules
        $rules = $auction->bid_rules;
        if (is_string($rules)) $rules = json_decode($rules, true);
        if (!is_array($rules)) $rules = [];

        $current = (float) $player->current_price;
        $decrement = 0;

        // Determine decrement amount from rules
        foreach ($rules as $r) {
            $from = isset($r['from']) ? (float) $r['from'] : 0;
            $to   = isset($r['to']) ? (float) $r['to'] : PHP_FLOAT_MAX;
            $inc  = isset($r['increment']) ? (float) $r['increment'] : 0;

            if ($current >= $from && $current <= $to) {
                $decrement = $inc;
                break;
            }
        }

        // If no matching rule, pick first rule where 'from' > current
        if ($decrement == 0) {
            foreach ($rules as $r) {
                $from = isset($r['from']) ? (float) $r['from'] : 0;
                $inc  = isset($r['increment']) ? (float) $r['increment'] : 0;
                if ($current > $from) {
                    $decrement = $inc;
                    break;
                }
            }
        }

        if ($decrement == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot decrease further. Minimum reached.',
                'current_price' => $current
            ], 400);
        }

        // Apply decrement, never go below base price
        $newPrice = max($player->base_price ?? 0, $current - $decrement);
        $player->current_price = $newPrice;
        $player->final_price = $newPrice;
        $player->save();

        // Create bid record for history (optional)
        AuctionBid::updateOrCreate(
            [
                'auction_id'        => $auction->id,
                'auction_player_id' => $player->id,
                'team_id'           => $data['teamId'] ?? null,
                'user_id'           => auth()->id(),
            ],
            [
                'player_id'         => $player->player_id,
                'amount'            => $newPrice,
            ]
        );


        // Load relationships for frontend
        $player->load([
            'player.player_type',
            'player.batting_profile',
            'player.bowling_profile',
        ]);

        $team = ActualTeam::find($data['teamId'] ?? null);

        // Broadcast live update
        broadcast(new PlayerOnBidEvent($player, $team))->toOthers();

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


    /**
     * Update the main auction configuration.
     * Player pool additions/removals are handled via AJAX.
     * Player base price updates can be via AJAX or form submission.
     */
    public function update(Request $request, Auction $auction)
    {
        $this->authorize('auction.edit');

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
            // Player IDs and prices are handled via AJAX, not directly validated here,
            // but if they are part of the form submission (hidden fields), we need to ensure they are present.
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'exists:players,id',
            'player_base_prices' => 'nullable|array',
            'player_base_prices.*' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $auction, $request) {
            $auction->update([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'bid_rules' => $validated['bid_rules'],
            ]);

            // The player_ids and player_base_prices are now primarily managed by AJAX.
            // However, if the form is submitted, we still want to capture any manually
            // updated prices that weren't submitted via AJAX. The AJAX calls handle the
            // dynamic price updates, so this part might be redundant or need careful review
            // if the goal is ONLY AJAX for price updates.
            // For now, we'll assume the form submission might still send some prices.
            $playerBasePricesFromForm = $request->input('player_base_prices', []);
            $playersToUpdateFromForm = [];

            foreach ($playerBasePricesFromForm as $playerId => $price) {
                // Basic validation for price coming from form
                if (Validator::make(['price' => $price], ['price' => 'required|numeric|min:0'])->fails()) {
                    // Log or handle invalid price from form submission if necessary
                    continue;
                }
                $playersToUpdateFromForm[$playerId] = ['base_price' => $price];
            }

            if (!empty($playersToUpdateFromForm)) {
                foreach ($playersToUpdateFromForm as $playerId => $data) {
                    AuctionPlayer::where('auction_id', $auction->id)
                        ->where('player_id', $playerId)
                        ->update($data);
                }
            }
        });

        return redirect()->route('admin.auctions.index')->with('success', 'Auction configuration updated successfully.');
    }

    public function addPlayerToPool(Request $request, Auction $auction, Player $player)
    {
        $this->authorize('auction.edit');

        $validated = $request->validate(['base_price' => 'required|numeric|min:0']);
        $basePrice = $validated['base_price'];

        $response = null; // Variable to hold the response

        try {
            DB::transaction(function () use ($auction, $player, $basePrice, &$response) { // Pass response by reference
                $playerExists = $auction->auctionPlayers()->where('player_id', $player->id)->exists();

                if ($playerExists) {
                    AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $player->id)->update([
                        'base_price' => $basePrice,
                        'current_price' => $basePrice,
                    ]);
                    // Set the response for existing player case
                    $response = response()->json([
                        'message' => 'Player already in pool. Price updated.',
                        'player' => ['id' => $player->id, 'name' => $player->name, 'base_price' => $basePrice],
                    ]);
                } else {
                    $newAuctionPlayer = AuctionPlayer::create([
                        'auction_id' => $auction->id,
                        'player_id' => $player->id,
                        'organization_id' => $auction->organization_id,
                        'base_price' => $basePrice,
                        'current_price' => $basePrice,
                        'starting_price' => $basePrice,
                        'status' => 'waiting',
                    ]);
                    // Set the response for newly created player
                    $response = response()->json([
                        'message' => 'Player added to pool successfully.',
                        'player' => ['id' => $player->id, 'name' => $player->name, 'base_price' => $basePrice],
                    ], 201);
                }
            });

            // Return the response after the transaction has successfully committed
            return $response;
        } catch (\Exception $e) {
            Log::error("Error adding player {$player->id} to auction {$auction->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to add player. Please try again.'], 500);
        }
    }
    /**
     * Handles AJAX request to remove a player from the auction pool.
     */
    public function removePlayerFromPool(Request $request, Auction $auction, Player $player)
    {
        $this->authorize('auction.edit');

        try {
            // Use lockForUpdate to prevent race conditions during removal
            $deletedCount = DB::transaction(function () use ($auction, $player) {
                return AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $player->id)
                    ->delete();
            });

            if ($deletedCount === 0) {
                // Player was not found in the pool for this auction, which can happen if already removed or never added.
                // Return a specific status for this case.
                return response()->json(['message' => 'Player not found in the pool for this auction.', 'player_id' => $player->id], 404);
            }

            return response()->json(['message' => 'Player removed from pool successfully.', 'player_id' => $player->id], 200);
        } catch (\Exception $e) {
            Log::error("Error removing player {$player->id} from auction {$auction->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to remove player. Please try again.'], 500);
        }
    }

    /**
     * Handles AJAX request to update a player's base price in the pool.
     */
    public function updatePlayerPrice(Request $request, Auction $auction, Player $player)
    {
        $this->authorize('auction.edit');

        // Validate the incoming base_price
        $validated = $request->validate([
            'base_price' => 'required|numeric|min:0',
        ]);

        $newBasePrice = $validated['base_price'];

        try {
            // Use lockForUpdate for consistency, especially if price changes affect other logic
            $updatedCount = DB::transaction(function () use ($auction, $player, $newBasePrice) {
                return AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $player->id)
                    ->update([
                        'base_price' => $newBasePrice,
                        'current_price' => $newBasePrice, // Assuming current_price also updates
                        // You might also want to update status or other fields if applicable
                    ]);
            });

            if ($updatedCount === 0) {
                return response()->json(['message' => 'Player or auction not found for price update.', 'player_id' => $player->id], 404);
            }

            return response()->json([
                'message' => 'Player base price updated successfully.',
                'player' => [
                    'id' => $player->id,
                    'base_price' => $newBasePrice,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error updating price for player {$player->id} in auction {$auction->id}: " . $e->getMessage());
            return response()->json(['error' => 'Failed to update player price. Please try again.'], 500);
        }
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

        // Find the player's current team (if any)
        $teamId = $auctionPlayer->sold_to_team_id; // or actual_team_id if applicable

        // Delete the auction player from the auction pool
        $auctionPlayer->delete();

        // If you still want to add them to actual_team_users (only if a team exists)
        if ($teamId) {
            ActualTeamUser::where('actual_team_id', $teamId)
                ->where('user_id', $auctionPlayer->player->user_id)
                ->delete();
        }


        return response()->json([
            'success' => true,
            'message' => 'Player removed from pool.'
        ]);
    }




    public function assignPlayer(Request $request)
    {
        $this->authorize('auction.edit');

        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'team_id' => 'required|exists:actual_teams,id',
            'final_price' => 'nullable|numeric|min:0',
        ]);

        $auctionPlayer = AuctionPlayer::findOrFail($validated['auction_player_id']);
        $team = ActualTeam::findOrFail($validated['team_id']);
        $auction = $auctionPlayer->auction;

        // Calculate total spent budget for the team
        $spentBudget = AuctionPlayer::where('auction_id', $auction->id)
            ->where('sold_to_team_id', $team->id)
            ->sum('final_price');

        $availableBalance = $auction->max_budget_per_team - $spentBudget;

        // Use final_price from request, or fallback to current/base price
        $newPrice = $request->final_price ?? ($auctionPlayer->current_price ?? $auctionPlayer->base_price);

        if ($newPrice > $availableBalance) {
            return response()->json([
                'error' => 'Insufficient team balance. Available: ' . number_format($availableBalance / 1000000, 1) . 'M'
            ], 400);
        }

        DB::transaction(function () use ($auctionPlayer, $team, $newPrice, $auction) {

            // Log the bid transaction
            AuctionBid::create([
                'auction_id' => $auction->id,
                'auction_player_id' => $auctionPlayer->id,
                'player_id' => $auctionPlayer->player_id,
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'amount' => $newPrice,
            ]);

            // Mark player as sold
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => $newPrice,
                'current_price' => $newPrice,
                'current_bid_team_id' => $team->id,
            ]);

            // Update main player status
            $auctionPlayer->player()->update([
                'player_mode' => 'sold',
                'actual_team_id' => $team->id,
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

            // Broadcast player sold event with team info
        });
        broadcast(new PlayerSoldEvent($auctionPlayer, $team));

        return back()->with('success', 'Player has been successfully assigned, sold, and added to the team.');
    }



    // putBackInAuction

    public function toggleStatus(Request $request, Auction $auction, $playerId)
    {
        $request->validate([
            'status' => 'required',
        ]);

        $auctionPlayer = $auction->auctionPlayers()->findOrFail($playerId);



        $auctionPlayer->status = $request->status;
        $auctionPlayer->save();


        $teamId = $auctionPlayer->sold_to_team_id; // or actual_team_id if applicable



        if ($teamId && $request->status != 'sold') {
            // Remove from team if not sold
            ActualTeamUser::where('actual_team_id', $teamId)
                ->where('user_id', $auctionPlayer->player->user_id)
                ->delete();
        } else if ($request->status == 'sold') {
            // Add to team if sold
            ActualTeamUser::updateOrCreate(
                [
                    'actual_team_id' => $teamId,
                    'user_id' => $auctionPlayer->player->user_id,
                ],
                [
                    'role' => 'Player',
                ]
            );
        }



        return response()->json([
            'success' => true,
            'status' => $auctionPlayer->status
        ]);
    }
}
