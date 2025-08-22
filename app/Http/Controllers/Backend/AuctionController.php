<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuctionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $auctions = $this->getFilteredAuctions($request)->paginate(100);

            return response()->json([
                'html' => view('backend.pages.auctions.partials.auction_table', compact('auctions'))->render(),
            ]);
        }

        $breadcrumbs = ['title' => __('Auctions')];
        $teams = Team::all();
        $auctions = $this->getFilteredAuctions($request)->paginate(100);

        return view('backend.pages.auctions.index', compact('auctions', 'breadcrumbs', 'teams'));
    }

    public function clearTeamData(ActualTeam $team)
    {
       
        // Example: remove all bids and reset players sold to this team
        DB::transaction(function () use ($team) {
            // Delete all bids for this team
            AuctionBid::where('team_id', $team->id)->delete();

            // Reset players assigned to this team
            AuctionPlayer::where('sold_to_team_id', $team->id)
                ->update([
                    'sold_to_team_id' => null,
                    'final_price' => null,
                ]);
        });

        return redirect()->back()->with('success', 'Auction data cleared for team: ' . $team->name);
    }


    // Extract filtering logic
    private function getFilteredAuctions(Request $request)
    {
        $query = Auction::with(['soldToTeam', 'player']);

        if ($request->filled('status')) {
            $query->where('auction_status', $request->status);
        }

        if ($request->filled('sold_to_team_id')) {
            $query->where('sold_to_team_id', $request->sold_to_team_id);
        }

        if ($request->filled('player_search')) {
            $search = $request->player_search;
            $query->whereHas('player', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $sortOrder = $request->filled('sort_order') && in_array(strtolower($request->sort_order), ['asc', 'desc']) ? $request->sort_order : 'asc';
            $query->orderBy($request->sort_by, $sortOrder);
        } else {
            $query->orderByDesc('updated_at'); // last updated first
        }

        return $query;
    }



    public function create()
    {
        $organizations = Organization::all();
        $tournaments = Tournament::all();
        $breadcrumbs = ['title' => 'Create Auction'];

        return view('backend.pages.auctions.create', compact('organizations', 'tournaments', 'breadcrumbs'));
    }


    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name'              => 'required|string|max:255',
    //         'organization_id' => 'required|exists:organizations,id',
    //         'tournament_id' => 'required|exists:tournaments,id',
    //         'start_at'          => 'required|date',

    //         'end_at'            => 'nullable|date|after_or_equal:start_at',
    //         'base_price'        => 'nullable|numeric|min:0',
    //         'max_bid_per_player' => 'nullable|numeric|min:0',
    //         'max_budget_per_team' => 'nullable|numeric|min:0',
    //     ]);

    //     Auction::create($validated);

    //     return redirect()
    //         ->route('admin.auctions.index')
    //         ->with('success', 'Auction created successfully.');
    // }





    public function store(Request $request)
    {
        // 1. Authorize the action
        $this->authorize('auction.create');

        // 2. Validate the essential data from the form
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:auctions,name',
            'organization_id' => [Auth::user()->hasRole('Superadmin') ? 'required' : 'nullable', 'exists:organizations,id'],
            'tournament_id' => 'required|exists:tournaments,id',

            'max_budget_per_team' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'bid_rules' => 'required|array|min:1',
        ]);

        // 3. Set the organization ID for non-Superadmin users
        if (!Auth::user()->hasRole('Superadmin')) {
            $validated['organization_id'] = Auth::user()->organization_id;
            if (!$validated['organization_id']) {
                return back()->with('error', 'You are not assigned to an organization.');
            }
        }

        $auction = null; // Initialize auction variable

        // 4. Use a database transaction for safety
        DB::transaction(function () use ($validated, &$auction) {

            // a. Create the main auction record. This part is based on your working code.
            $auction = Auction::create($validated);

            // b. AUTOMATION: Find all players who are eligible for this auction
            $eligiblePlayers = Player::query()
                ->whereNotNull('welcome_email_sent_at') // Must be verified
                ->where(function ($query) {
                    $query->where('player_mode', '!=', 'retained')
                        ->orWhereNull('player_mode');
                })
                ->whereHas('user', function ($query) use ($auction) {
                    // Must belong to the same organization as the new auction
                    $query->where('organization_id', $auction->organization_id);
                })
                ->whereDoesntHave('auctionPlayers.auction', function ($query) use ($auction) {
                    // Must not be in another auction for the same tournament
                    $query->where('tournament_id', $auction->tournament_id);
                })
                ->get();

            // c. Prepare the data for the 'auction_players' table
            $auctionPlayersData = [];
            foreach ($eligiblePlayers as $player) {
                $auctionPlayersData[] = [
                    'auction_id' => $auction->id,
                    'player_id' => $player->id,
                    'organization_id' => $auction->organization_id,
                    'base_price' => $auction->base_price,
                    'current_price' => $auction->base_price, // Current price starts at base price
                    'starting_price' => $auction->base_price, // Or $player->specific_base_price if you have one

                    'status' => 'waiting', // All players start as 'pending'
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // d. Bulk insert all the eligible players into the auction pool
            if (!empty($auctionPlayersData)) {
                AuctionPlayer::insert($auctionPlayersData);
            }
        });

        // 5. Redirect to the auction's "show" page to see the result
        if ($auction) {
            return redirect()->route('admin.auctions.show', $auction)->with('success', 'Auction created and player pool has been automatically populated!');
        }

        // Fallback redirect in case of a transaction failure
        return redirect()->route('admin.auctions.index')->with('error', 'There was an error creating the auction.');
    }

    public function show(Auction $auction)
    {
        $breadcrumbs = ['title' => __('Auction Details')];

        // **THE FIX**: Eager-load the auction players and their nested player profiles.
        // This is a crucial performance optimization.
        $auction->load([
            'auctionPlayers.player.playerType',
            'auctionPlayers.player.user.organization' // Load whatever details you need
        ]);

        return view('backend.pages.auctions.show', compact('auction', 'breadcrumbs'));
    }
    public function edit(Auction $auction)
    {
        $this->authorize('auction.edit'); // Assuming a permission

        $breadcrumbs = ['title' => __('Edit Auction')];
        $organizations = Organization::orderBy('name')->get();
        $tournaments = Tournament::orderBy('name')->get();

        // =======================================================
        // **THE FIX IS HERE**
        // We need to build the list of players who can be added to the auction.
        // =======================================================

        // 1. First, get the IDs of players who are ALREADY in this auction pool.
        // We use load() for efficiency.
        $auction->load('auctionPlayers.player');
        $playersAlreadyInPoolIds = $auction->auctionPlayers->pluck('player.id')->toArray();

        // 2. Now, find all players who are "available" for any auction.
        // An available player is:
        //    a) Verified (welcome email has been sent)
        //    b) Not already retained by a team
        //    c) Belongs to the same organization as the auction
        //    d) Is NOT already in this auction's pool
        $availablePlayers = Player::query()
            ->whereNotNull('welcome_email_sent_at') // Must be verified
            ->where(function ($query) {
                $query->where('player_mode', '!=', 'retained') // Must not be retained
                    ->orWhereNull('player_mode');
            })
            ->whereHas('user', function ($query) use ($auction) {
                // Must belong to the same organization as the auction
                $query->where('organization_id', $auction->organization_id);
            })
            ->whereNotIn('id', $playersAlreadyInPoolIds) // Must not already be in this pool
            ->orderBy('name')
            ->get();

        // 3. Pass all the necessary data to the view.
        return view('backend.pages.auctions.edit', compact(
            'auction',
            'breadcrumbs',
            'organizations',
            'tournaments',
            'availablePlayers' // <-- Now passing the required variable
        ));
    }


    public function update(Request $request, Auction $auction)
    {
        // 1. Authorize the action
        $this->authorize('auctions.edit');

        // 2. Validate ALL the data from your multi-step form
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
            'tournament_id' => 'required|exists:tournaments,id',

            // **FIX 1**: Corrected the 'status' validation rule
            'status' => 'required|string|in:pending,live,completed,scheduled,paused',

            'max_budget_per_team' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'bid_rules' => 'required|array|min:1',
            'bid_rules.*.from' => 'required|numeric|min:0',
            'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
            'bid_rules.*.increment' => 'required|numeric|min:0',
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'exists:players,id',
            'player_base_prices' => 'nullable|array',
            'player_base_prices.*' => 'required|numeric|min:0',
        ]);

        // Use a database transaction for safety
        DB::transaction(function () use ($validated, $auction) {

            // 3. Update the main auction details
            $auction->update([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'bid_rules' => $validated['bid_rules'],
            ]);

            // 4. Synchronize the Player Pool from step 4
            $playerIdsInPool = $validated['player_ids'] ?? [];
            $basePrices = $validated['player_base_prices'] ?? [];
            $existingPlayerIds = $auction->auctionPlayers()->pluck('player_id')->toArray();

            $playersToAdd = array_diff($playerIdsInPool, $existingPlayerIds);

            $playersToRemove = array_diff($existingPlayerIds, $playerIdsInPool);
            $playersToUpdate = array_intersect($playerIdsInPool, $existingPlayerIds);

            // a) Remove players who were taken out of the pool
            if (!empty($playersToRemove)) {
                AuctionPlayer::where('auction_id', $auction->id)->whereIn('player_id', $playersToRemove)->delete();
            }

            // b) Add new players to the pool
            foreach ($playersToAdd as $playerId) {

                AuctionPlayer::create([
                    'auction_id' => $auction->id,
                    'player_id' => $playerId,
                    'base_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'starting_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'current_price' => $basePrices[$playerId] ?? $auction->base_price,
                    'organization_id' => $auction->organization_id,
                    'status' => 'waiting', // **FIX 2**: Corrected status to 'pending'
                ]);
            }

            // c) Update base prices for players who remained in the pool
            foreach ($playersToUpdate as $playerId) {
                if (isset($basePrices[$playerId])) {
                    AuctionPlayer::where('auction_id', $auction->id)
                        ->where('player_id', $playerId)
                        ->update([
                            'base_price' => $basePrices[$playerId],
                            'current_price' => $basePrices[$playerId] // Also reset current price
                        ]);
                }
            }
        });

        // 5. Redirect with a success message
        return redirect()->route('admin.auctions.index')->with('success', 'Auction and player pool updated successfully.');
    }
    public function destroy(Auction $auction)
    {
        $auction->delete();
        return redirect()->route('admin.auctions.index')->with('success', 'Auction deleted successfully.');
    }
}
