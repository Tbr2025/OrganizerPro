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
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use Illuminate\Http\JsonResponse;
use App\Services\Auction\AuctionPoolService;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        // Fetch available players (approved, not retained) for the player pool step
        $orgId = Auth::user()->organization_id;
        $query = Player::where('status', 'approved')
            ->where(function ($q) {
                $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode');
            });
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }
        // Include organization_id so the wizard can filter the list to the
        // selected organization (a Superadmin picks the org in the form).
        $availablePlayers = $query->orderBy('name')->get(['id', 'name', 'organization_id']);

        return view('backend.pages.auctions.create', compact('organizations', 'tournaments', 'availablePlayers'));
    }

    public function store(Request $request)
    {
        $this->authorize('auction.create');

        // Check auction access (bypass for Superadmin)
        if (!Auth::user()->hasRole('Superadmin')) {
            $organization = Auth::user()->organization_id
                ? \App\Models\Organization::find(Auth::user()->organization_id)
                : null;

            if ($organization && !$organization->isAuctionEnabled()) {
                return redirect()->back()->withInput()
                    ->with('error', 'Auctions are not enabled for your organization. Please contact your administrator to upgrade your package.');
            }
        }

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
            'bid_type' => 'required|in:open,closed',
            'bid_timer_seconds' => 'required|integer|min:5|max:300',
            'bid_timer_reset_seconds' => 'nullable|integer|min:5|max:300',
            'online_bid_limit_from' => 'nullable|numeric|min:0',
            'online_bid_limit_to' => 'nullable|numeric|min:0|gt:online_bid_limit_from',
            'closed_bid_starts_at' => 'nullable|numeric|min:0',

            // Branding
            'background_image' => 'nullable|image|max:5120',
            'auction_logo' => 'nullable|image|max:5120',
            'waiting_background_image' => 'nullable|image|max:5120',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',

            // Player pool data (optional at creation)
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'exists:players,id',
            'player_base_prices' => 'nullable|array',
            'player_base_prices.*' => 'required|numeric|min:0',
            // Named pools (JSON from the wizard's pool builder)
            'pools' => 'nullable|string',
        ], $messages);

        if (!Auth::user()->hasRole('Superadmin')) {
            $validated['organization_id'] = Auth::user()->organization_id;
            if (!$validated['organization_id']) {
                return back()->with('error', 'You are not assigned to an organization and cannot create an auction.');
            }
        }

        // The tournament is the source of truth for which org's players are eligible —
        // keep the auction's org aligned to it so player isolation never mismatches.
        $validated['organization_id'] = Tournament::whereKey($validated['tournament_id'])->value('organization_id')
            ?: $validated['organization_id'];

        DB::transaction(function () use ($validated, $request) {

            // Handle branding uploads
            $brandingData = [];
            foreach (['background_image', 'auction_logo', 'waiting_background_image'] as $field) {
                if ($request->hasFile($field)) {
                    $brandingData[$field] = $request->file($field)->store('auction-branding', 'public');
                }
            }
            if (!empty($validated['primary_color'])) $brandingData['primary_color'] = $validated['primary_color'];
            if (!empty($validated['secondary_color'])) $brandingData['secondary_color'] = $validated['secondary_color'];

            // Create the auction
            $auction = Auction::create(array_merge([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'bid_rules' => $validated['bid_rules'],
                'bid_type' => $validated['bid_type'],
                'bid_timer_seconds' => $validated['bid_timer_seconds'],
                'bid_timer_reset_seconds' => $validated['bid_timer_reset_seconds'] ?? 15,
                'online_bid_limit_from' => $validated['online_bid_limit_from'] ?? null,
                'online_bid_limit_to' => $validated['online_bid_limit_to'] ?? null,
                'closed_bid_starts_at' => $validated['closed_bid_starts_at'] ?? null,
            ], $brandingData));

            // Named pools from the wizard builder (preferred), else flat player list.
            $pools = json_decode($validated['pools'] ?? '', true);

            if (is_array($pools) && count($pools)) {
                $this->persistAuctionPools($auction, $pools);
            } else {
                $playerIdsInPool = $validated['player_ids'] ?? [];
                $basePrices = $validated['player_base_prices'] ?? [];
                // Org isolation: drop any player not in the auction's organization
                // (skipped when the auction has no org so real players aren't lost).
                $validPlayerIds = $auction->organization_id
                    ? \App\Models\Player::withoutGlobalScopes()
                        ->where('organization_id', $auction->organization_id)
                        ->pluck('id')->flip()
                    : null;
                foreach ($playerIdsInPool as $playerId) {
                    if ($validPlayerIds !== null && ! isset($validPlayerIds[(int) $playerId])) {
                        continue;
                    }
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
            }
        });

        return redirect()->route('admin.auctions.index')->with('success', 'Auction configured and created successfully.');
    }

    /**
     * Create AuctionPool rows + their AuctionPlayer rows from the wizard's
     * pool builder JSON, then assign lot_number per each pool's order mode.
     *
     * @param  array<int, array{name?:string,capacity?:mixed,order_mode?:string,players?:array}>  $pools
     */
    protected function persistAuctionPools(Auction $auction, array $pools): void
    {
        // Fresh auction (store): no existing players to preserve.
        $this->buildPoolsFromData($auction, $pools, []);
    }

    /**
     * Non-destructive pool rebuild for an existing auction (update). Players that
     * have already been actioned (sold / on_auction / closed / unsold) are LEFT
     * UNTOUCHED; only the "waiting" layout + lot ordering is rebuilt from the JSON.
     *
     * @param  array<int, array{name?:string,capacity?:mixed,order_mode?:string,players?:array}>  $pools
     */
    protected function syncAuctionPools(Auction $auction, array $pools): void
    {
        // Players already in play keep their rows, pool grouping aside.
        $preservePlayerIds = AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', '!=', 'waiting')
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Drop the current waiting layout (pools + their waiting players).
        AuctionPlayer::where('auction_id', $auction->id)->where('status', 'waiting')->delete();
        AuctionPool::where('auction_id', $auction->id)->delete(); // FK nullOnDelete: sold players just lose grouping

        $this->buildPoolsFromData($auction, $pools, $preservePlayerIds);
    }

    /**
     * Shared pool/player creation from the wizard's pool-builder JSON. Org-isolates
     * players, skips any id in $skipPlayerIds (already-actioned), and assigns
     * lot_number per each pool's order mode.
     *
     * @param  array<int, array{name?:string,capacity?:mixed,order_mode?:string,players?:array}>  $pools
     * @param  array<int, int>  $skipPlayerIds
     */
    protected function buildPoolsFromData(Auction $auction, array $pools, array $skipPlayerIds = []): void
    {
        $poolService = app(AuctionPoolService::class);
        $skip = array_flip($skipPlayerIds);

        // Org isolation: only players belonging to the auction's organization may be
        // added — guards against cross-org assignment even if the UI is bypassed.
        // When the auction has no organization (legacy/global auctions), skip the
        // org filter entirely so real players aren't dropped.
        $validPlayerIds = $auction->organization_id
            ? \App\Models\Player::withoutGlobalScopes()
                ->where('organization_id', $auction->organization_id)
                ->pluck('id')
                ->flip()
            : null;

        // Retained players → flagged is_retained on their pool row (not auctioned until merged).
        $retainedIds = \App\Models\Player::withoutGlobalScopes()
            ->where('player_mode', 'retained')
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->pluck('id')->flip();

        foreach (array_values($pools) as $sequence => $poolData) {
            $players = is_array($poolData['players'] ?? null) ? $poolData['players'] : [];
            // Keep only org players that aren't already actioned.
            $players = array_values(array_filter($players, function ($pl) use ($validPlayerIds, $skip) {
                $id = (int) ($pl['id'] ?? 0);
                return ($validPlayerIds === null || isset($validPlayerIds[$id])) && ! isset($skip[$id]);
            }));
            if (! count($players)) {
                continue;
            }

            $mode = $poolData['order_mode'] ?? 'sequential';
            $pool = AuctionPool::create([
                'auction_id' => $auction->id,
                'organization_id' => $auction->organization_id,
                'name' => trim((string) ($poolData['name'] ?? 'Pool')) ?: 'Pool',
                'capacity' => is_numeric($poolData['capacity'] ?? null) ? (int) $poolData['capacity'] : null,
                'order_mode' => in_array($mode, ['sequential', 'random', 'odd_even', 'manual'], true) ? $mode : 'sequential',
                'sequence' => $sequence + 1,
            ]);

            foreach (array_values($players) as $i => $pl) {
                $playerId = (int) ($pl['id'] ?? 0);
                if (! $playerId) {
                    continue;
                }
                $base = is_numeric($pl['base_price'] ?? null) ? $pl['base_price'] : $auction->base_price;
                $isRetained = isset($retainedIds[$playerId]);
                AuctionPlayer::updateOrCreate(
                    ['auction_id' => $auction->id, 'player_id' => $playerId],
                    [
                        'auction_pool_id' => $pool->id,
                        'organization_id' => $auction->organization_id,
                        'base_price' => $base,
                        'current_price' => $base,
                        'starting_price' => $base,
                        'lot_number' => $isRetained ? null : ($i + 1), // retained players have no draw slot
                        'status' => 'waiting',
                        'is_retained' => $isRetained,
                    ]
                );
            }

            // Final lot order per the pool's rule (sequential/random/odd_even/manual).
            $poolService->generateLotNumbers($pool);
        }
    }

    /** Reorder pools (drag on the Show page) → persist each pool's sequence. */
    public function reorderPools(Request $request, Auction $auction): JsonResponse
    {
        $this->authorize('auction.edit');
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach (array_values($data['order']) as $i => $poolId) {
            AuctionPool::where('auction_id', $auction->id)->where('id', (int) $poolId)
                ->update(['sequence' => $i + 1]);
        }

        return response()->json(['success' => true]);
    }

    /** Re-draw lot numbers for a pool (re-applies its order mode; reshuffles Random). */
    public function redrawPool(Auction $auction, AuctionPool $pool): JsonResponse
    {
        $this->authorize('auction.edit');
        abort_unless($pool->auction_id === $auction->id, 404);

        app(AuctionPoolService::class)->generateLotNumbers($pool);

        return response()->json([
            'success' => true,
            'lots' => $pool->players()->where('is_retained', false)->orderBy('lot_number')
                ->with('player:id,name')->get(['id', 'player_id', 'lot_number']),
        ]);
    }

    /** Merge a pool's retained members into the auction (make them biddable/waiting). */
    public function mergeRetained(Request $request, Auction $auction, AuctionPool $pool): JsonResponse
    {
        $this->authorize('auction.edit');
        abort_unless($pool->auction_id === $auction->id, 404);

        $data = $request->validate([
            'auction_player_ids' => 'nullable|array',
            'auction_player_ids.*' => 'integer',
        ]);

        $query = $pool->players()->where('is_retained', true);
        if (! empty($data['auction_player_ids'])) {
            $query->whereIn('id', $data['auction_player_ids']);
        }
        $merged = $query->update(['is_retained' => false, 'status' => 'waiting', 'lot_number' => null]);

        // Slot the merged players into the pool's draw order.
        app(AuctionPoolService::class)->generateLotNumbers($pool);

        return response()->json(['success' => true, 'merged' => $merged]);
    }


    public function show(Auction $auction)
    {
        $this->authorize('auction.view');

        $user = auth()->user();
        $isAdmin = $user->hasRole(['Superadmin', 'Admin']);

        $auction->load([
            'organization',
            'tournament',
            'auctionPlayers.player.playerType',
            'auctionPlayers.player.battingProfile',
            'auctionPlayers.player.bowlingProfile',
            'auctionPlayers.soldToTeam',
            'auctionPlayers.bids.team',
            'pools.players.player',
            'pools.players.soldToTeam',
        ]);

        // For non-admin users, filter to only show players sold to their team
        $userTeam = null;
        if (!$isAdmin) {
            // Get user's team for this tournament
            $userTeam = $user->actualTeams()
                ->forTournament($auction->tournament_id)
                ->first();

            if ($userTeam) {
                // Filter auction players to only those sold to user's team
                $auction->setRelation(
                    'auctionPlayers',
                    $auction->auctionPlayers->filter(function ($player) use ($userTeam) {
                        return $player->sold_to_team_id === $userTeam->id;
                    })->values()
                );
            } else {
                // User has no team in this tournament - show empty
                $auction->setRelation('auctionPlayers', collect());
            }
        }

        $teams = ActualTeam::forTournament($auction->tournament_id)
            ->orderBy('name')
            ->get();

        // Decode bid_rules JSON from the DB
        $bidRules = is_string($auction->bid_rules)
            ? json_decode($auction->bid_rules, true)
            : $auction->bid_rules; // Already array if cast in model


        return view('backend.pages.auctions.show', [
            'auction'   => $auction,
            'teams'     => $teams,
            'bidRules'  => $bidRules,
            'isAdmin'   => $isAdmin,
            'userTeam'  => $userTeam
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
            'auctionPlayers.player.battingProfile',
            'auctionPlayers.player.bowlingProfile',
            'auctionPlayers.soldToTeam'
        ]);

        // Start query on auctionPlayers
        $query = $auction->auctionPlayers()->with([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
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
        $teams = ActualTeam::forTournament($auction->tournament_id)
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

        try {
            $result = DB::transaction(function () use ($data, $auction) {
                $player = AuctionPlayer::where('auction_id', $auction->id)
                    ->lockForUpdate()
                    ->findOrFail($data['playerID']);

                // Prevent consecutive bids by the same team
                if ($data['teamId'] && $player->current_bid_team_id == $data['teamId']) {
                    throw new \Exception('This team is already the highest bidder.');
                }

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
                    throw new \Exception('Maximum bid reached.');
                }

                $newPrice = $current + $increment;
                $player->current_price = $newPrice;
                $player->final_price = $newPrice;
                $player->current_bid_team_id = $data['teamId'] ?? null;
                $player->save();

                // Determine bid source based on current auction mode
                $bidSource = $auction->isOfflineMode() ? 'offline' : 'online';

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
                        'bid_source'        => $bidSource,
                    ]
                );

                return ['newPrice' => $newPrice, 'increment' => $increment, 'player' => $player];
            });
        } catch (\Exception $e) {
            $player = AuctionPlayer::where('auction_id', $auction->id)->find($data['playerID']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'current_price' => (float) ($player?->current_price ?? 0),
            ], 422);
        }

        $newPrice = $result['newPrice'];
        $increment = $result['increment'];
        $player = $result['player'];

        // Auto-transition: open → closed (if threshold configured and not manually overridden)
        if ($auction->hasAutoPhaseTransition()
            && !$auction->mode_manually_overridden
            && $auction->bid_type === 'open'
            && $newPrice >= (float) $auction->closed_bid_starts_at) {
            $auction->update(['bid_type' => 'closed']);
        }

        // Auto-transition to offline if price exceeds online limit
        $auction = $auction->fresh();
        if ($auction->hasOnlineOfflineMode()
            && !$auction->mode_manually_overridden
            && $newPrice > (float) $auction->online_bid_limit_to) {
            $auction->update(['open_bid_mode' => 'offline']);
        }

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
            'increment_used' => $increment,
            'open_bid_mode'  => $auction->fresh()->open_bid_mode,
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

        // Load the players currently in the auction, its pools, tournament, and budgets.
        $auction->load(['auctionPlayers.player', 'pools.players.player', 'tournament', 'teamBudgets']);
        $auctionPlayerIds = $auction->auctionPlayers->pluck('player.id')->toArray();

        // Available = approved players not already in this auction. Retained players
        // ARE selectable (flagged) so they can be placed in a pool and merged later.
        $availablePlayers = Player::where('status', 'approved')
            ->whereNotIn('id', $auctionPlayerIds)
            ->when($auction->organization_id, function ($query) use ($auction) {
                $query->where('organization_id', $auction->organization_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id', 'player_mode'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'organization_id' => $p->organization_id,
                'retained' => $p->player_mode === 'retained',
            ]);

        // Existing pools (sequence order) with their players (lot order) for the wizard.
        $existingPools = $auction->pools->sortBy('sequence')->values()->map(function ($pool) {
            return [
                'id' => $pool->id,
                'name' => $pool->name,
                'capacity' => $pool->capacity,
                'order_mode' => $pool->order_mode,
                'players' => $pool->players->sortBy('lot_number')->values()->map(fn ($ap) => [
                    'id' => $ap->player_id,
                    'name' => $ap->player->name ?? ('Player #' . $ap->player_id),
                    'base_price' => $ap->base_price,
                    'org' => $ap->organization_id,
                    'retained' => (bool) $ap->is_retained,
                ])->all(),
            ];
        })->all();

        // Players in the auction but not assigned to any pool (legacy) — surface them
        // so the wizard never silently drops them.
        $pooledIds = collect($existingPools)->pluck('players')->flatten(1)->pluck('id')->all();
        $unpooled = $auction->auctionPlayers
            ->whereNull('auction_pool_id')
            ->map(fn ($ap) => [
                'id' => $ap->player_id,
                'name' => $ap->player->name ?? ('Player #' . $ap->player_id),
                'base_price' => $ap->base_price,
                'org' => $ap->organization_id,
                'retained' => (bool) $ap->is_retained,
            ])->values()->all();

        // Teams in this auction's tournament + their existing per-team budgets.
        $budgetTeams = $auction->tournament_id
            ? ActualTeam::forTournament($auction->tournament_id)->orderBy('name')->get()
            : collect();
        $teamBudgets = $auction->teamBudgets->keyBy('actual_team_id'); // actual_team_id => AuctionTeamBudget

        return view('backend.pages.auctions.edit', compact(
            'auction', 'organizations', 'tournaments', 'availablePlayers', 'existingPools', 'unpooled',
            'budgetTeams', 'teamBudgets'
        ));
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
            'bid_type' => 'required|in:open,closed',
            'bid_timer_seconds' => 'required|integer|min:5|max:300',
            'bid_timer_reset_seconds' => 'nullable|integer|min:5|max:300',
            'online_bid_limit_from' => 'nullable|numeric|min:0',
            'online_bid_limit_to' => 'nullable|numeric|min:0|gt:online_bid_limit_from',
            'closed_bid_starts_at' => 'nullable|numeric|min:0',
            // Branding
            'background_image' => 'nullable|image|max:5120',
            'auction_logo' => 'nullable|image|max:5120',
            'waiting_background_image' => 'nullable|image|max:5120',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            // Player IDs and prices are handled via AJAX, not directly validated here,
            // but if they are part of the form submission (hidden fields), we need to ensure they are present.
            'player_ids' => 'nullable|array',
            'player_ids.*' => 'exists:players,id',
            'player_base_prices' => 'nullable|array',
            'player_base_prices.*' => 'required|numeric|min:0',
            // Pool builder (same as create); when present it drives the player layout.
            'pools' => 'nullable|string',
            // Per-team budget overrides (blank = uniform cap).
            'team_budgets' => 'nullable|array',
            'team_budgets.*' => 'nullable|numeric|min:0',
        ]);

        // Keep the auction's org aligned to its tournament so player isolation never
        // mismatches (prevents the pool sync from dropping legitimate players).
        $validated['organization_id'] = Tournament::whereKey($validated['tournament_id'])->value('organization_id')
            ?: $validated['organization_id'];

        DB::transaction(function () use ($validated, $auction, $request) {
            // Handle branding uploads — delete old file on replacement
            $brandingData = [];
            foreach (['background_image', 'auction_logo', 'waiting_background_image'] as $field) {
                if ($request->hasFile($field)) {
                    if ($auction->$field) {
                        Storage::disk('public')->delete($auction->$field);
                    }
                    $brandingData[$field] = $request->file($field)->store('auction-branding', 'public');
                }
            }
            if ($request->has('primary_color')) $brandingData['primary_color'] = $validated['primary_color'];
            if ($request->has('secondary_color')) $brandingData['secondary_color'] = $validated['secondary_color'];

            $auction->update(array_merge([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'bid_rules' => $validated['bid_rules'],
                'bid_type' => $validated['bid_type'],
                'bid_timer_seconds' => $validated['bid_timer_seconds'],
                'bid_timer_reset_seconds' => $validated['bid_timer_reset_seconds'] ?? 15,
                'online_bid_limit_from' => $validated['online_bid_limit_from'] ?? null,
                'online_bid_limit_to' => $validated['online_bid_limit_to'] ?? null,
                'closed_bid_starts_at' => $validated['closed_bid_starts_at'] ?? null,
            ], $brandingData));

            // Preferred path: the pool-builder JSON (same as create) drives the player
            // layout. Rebuilds the "waiting" players/pools/lots; never touches players
            // already sold/on-auction/closed.
            $pools = json_decode($validated['pools'] ?? '', true);
            if (is_array($pools) && count($pools)) {
                $this->syncAuctionPools($auction, $pools);
            } else {
                // Legacy fallback: flat player_base_prices map (older edit form).
                $playerBasePricesFromForm = $request->input('player_base_prices', []);
                foreach ($playerBasePricesFromForm as $playerId => $price) {
                    if (Validator::make(['price' => $price], ['price' => 'required|numeric|min:0'])->fails()) {
                        continue;
                    }
                    AuctionPlayer::where('auction_id', $auction->id)
                        ->where('player_id', $playerId)
                        ->update(['base_price' => $price]);
                }
            }

            // Per-team budget overrides (blank clears the override → uniform cap applies).
            if (is_array($request->input('team_budgets'))) {
                foreach ($request->input('team_budgets') as $teamId => $budget) {
                    if ($budget === null || $budget === '') {
                        AuctionTeamBudget::where('auction_id', $auction->id)
                            ->where('actual_team_id', (int) $teamId)->delete();
                        continue;
                    }
                    AuctionTeamBudget::updateOrCreate(
                        ['auction_id' => $auction->id, 'actual_team_id' => (int) $teamId],
                        ['organization_id' => $auction->organization_id, 'budget' => $budget]
                    );
                }
            }
        });

        return redirect()->route('admin.auctions.index')->with('success', 'Auction configuration updated successfully.');
    }

    public function removeBrandingImage(Request $request, Auction $auction)
    {
        $this->authorize('auction.edit');

        $field = $request->input('field');
        $allowed = ['background_image', 'auction_logo', 'waiting_background_image'];

        if (!in_array($field, $allowed)) {
            return response()->json(['error' => 'Invalid field.'], 422);
        }

        if ($auction->$field) {
            Storage::disk('public')->delete($auction->$field);
            $auction->update([$field => null]);
        }

        return response()->json(['success' => true, 'message' => 'Image removed.']);
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



    /**
     * Display the auction report with bid history, highlights, and team breakdown.
     */
    public function report(Auction $auction)
    {
        $this->authorize('auction.view');

        $auction->load([
            'organization',
            'tournament',
            'auctionPlayers.player.playerType',
            'auctionPlayers.soldToTeam',
            'auctionPlayers.bids' => function ($q) {
                $q->with(['team', 'user'])->orderBy('created_at', 'asc');
            },
        ]);

        $teams = ActualTeam::forTournament($auction->tournament_id)
            ->orderBy('name')
            ->get();

        // --- Build per-player bid data with gap calculation ---
        $tieBidIds = [];
        $closeBidIds = [];
        $playerBidData = [];

        foreach ($auction->auctionPlayers as $ap) {
            $bids = $ap->bids->sortBy('created_at')->values();
            $bidsWithGap = [];

            foreach ($bids as $i => $bid) {
                $gap = null;
                if ($i > 0) {
                    $gap = $bid->created_at->diffInSeconds($bids[$i - 1]->created_at);
                }
                $bidsWithGap[] = [
                    'id' => $bid->id,
                    'team_id' => $bid->team_id,
                    'team_name' => $bid->team->name ?? 'N/A',
                    'user_name' => $bid->user->name ?? 'N/A',
                    'amount' => $bid->amount,
                    'bid_source' => $bid->bid_source,
                    'created_at' => $bid->created_at->format('h:i:s A'),
                    'gap' => $gap,
                ];
            }

            // Tie detection: group bids by amount → if 2+ distinct teams have same amount
            $byAmount = $bids->groupBy('amount');
            foreach ($byAmount as $amount => $group) {
                $distinctTeams = $group->pluck('team_id')->unique()->filter()->count();
                if ($distinctTeams >= 2) {
                    foreach ($group as $bid) {
                        $tieBidIds[] = $bid->id;
                    }
                }
            }

            // Close-time detection: consecutive bids within 2 seconds
            for ($i = 1; $i < count($bids); $i++) {
                $diff = $bids[$i]->created_at->diffInSeconds($bids[$i - 1]->created_at);
                if ($diff <= 2) {
                    $closeBidIds[] = $bids[$i]->id;
                    $closeBidIds[] = $bids[$i - 1]->id;
                }
            }

            $playerBidData[$ap->id] = $bidsWithGap;
        }

        $tieBidIds = array_unique($tieBidIds);
        $closeBidIds = array_unique($closeBidIds);

        // --- Summary stats ---
        $soldPlayers = $auction->auctionPlayers->where('status', 'sold');
        $unsoldPlayers = $auction->auctionPlayers->whereIn('status', ['unsold', 'waiting']);
        $totalBids = $auction->auctionPlayers->sum(fn($ap) => $ap->bids->count());
        $totalRevenue = $soldPlayers->sum('final_price');
        $highestSale = $soldPlayers->max('final_price');
        $avgPrice = $soldPlayers->count() > 0 ? $totalRevenue / $soldPlayers->count() : 0;
        $mostExpensivePlayer = $soldPlayers->sortByDesc('final_price')->first();

        $summary = [
            'sold_count' => $soldPlayers->count(),
            'unsold_count' => $unsoldPlayers->count(),
            'total_bids' => $totalBids,
            'total_players' => $auction->auctionPlayers->count(),
            'total_revenue' => $totalRevenue,
            'highest_sale' => $highestSale,
            'avg_price' => $avgPrice,
            'most_expensive_player' => $mostExpensivePlayer,
        ];

        // --- Team summaries ---
        $teamSummaries = [];
        foreach ($teams as $team) {
            $teamPlayers = $soldPlayers->where('sold_to_team_id', $team->id);
            $totalSpent = $teamPlayers->sum('final_price');
            $teamBidsCount = $auction->auctionPlayers->sum(fn($ap) => $ap->bids->where('team_id', $team->id)->count());
            $avgTeamPrice = $teamPlayers->count() > 0 ? $totalSpent / $teamPlayers->count() : 0;
            $budget = (float) $auction->max_budget_per_team;
            $utilization = $budget > 0 ? ($totalSpent / $budget) * 100 : 0;

            $teamSummaries[] = [
                'team' => $team,
                'players_bought' => $teamPlayers->count(),
                'total_spent' => $totalSpent,
                'remaining_budget' => $budget - $totalSpent,
                'total_bids' => $teamBidsCount,
                'avg_price' => $avgTeamPrice,
                'budget_utilization' => round($utilization, 1),
                'acquired_players' => $teamPlayers->values(),
            ];
        }

        $breadcrumbs = ['title' => __('Auction Report')];

        return view('backend.pages.auctions.report', [
            'auction' => $auction,
            'teams' => $teams,
            'playerBidData' => $playerBidData,
            'tieBidIds' => $tieBidIds,
            'closeBidIds' => $closeBidIds,
            'summary' => $summary,
            'teamSummaries' => $teamSummaries,
            'breadcrumbs' => $breadcrumbs,
        ]);
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
