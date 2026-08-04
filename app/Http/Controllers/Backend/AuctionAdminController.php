<?php

namespace App\Http\Controllers\Backend;

use App\Events\PlayerOnBidEvent;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use Illuminate\Http\JsonResponse;
use App\Models\AuctionActionLog;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\BidIncrementService;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuctionAdminController extends Controller
{
    /**
     * Reject a squad-reserve configuration that can never be satisfied.
     *
     * The reserve holds back `(slots_remaining - 1) * min_price_per_player`, so unless
     * a full squad fits inside the purse the maximum allowable bid is zero and no
     * player can ever be bought. Catching it at save time is the difference between a
     * clear validation message and an auction that silently refuses every bid on the
     * day.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function assertSquadReserveIsSatisfiable(array $validated): void
    {
        $budget = (float) ($validated['max_budget_per_team'] ?? 0);
        $squad = (int) ($validated['min_squad_size'] ?? Auction::DEFAULT_MIN_SQUAD_SIZE);
        $minPrice = $validated['min_price_per_player'] ?? null;

        // Blank means "fall back to base price", which is validated as <= budget by
        // virtue of being a single player's price.
        if ($minPrice === null || $minPrice === '' || $budget <= 0 || $squad < 1) {
            return;
        }

        $required = $squad * (float) $minPrice;

        if ($required > $budget) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'min_price_per_player' => sprintf(
                    'A squad of %d at %s each needs %s, but the per-team budget is only %s. '
                    . 'Lower the minimum price per player or the minimum squad size, or raise the budget.',
                    $squad,
                    format_points($minPrice),
                    format_points($required),
                    format_points($budget)
                ),
            ]);
        }
    }

    /**
     * Display a list of all auctions.
     */
    public function index()
    {

        $query = Auction::with('tournament', 'organization');
        if (! Auth::user()->hasRole('Superadmin')) {
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
        // Auctions attach only to auction-type tournaments (budget/pools apply there).
        $tournaments = Tournament::forUser(auth()->user())->where('type', 'auction')->orderBy('name')->get();

        // Available players for the pool builder: approved somewhere, and never retained
        // (retained players are pre-kept by their team and managed on the Pools screen).
        //
        // The tournament is chosen inside the form, so it cannot be filtered here —
        // instead each player carries the tournaments they are approved for and the
        // wizard narrows the list the moment a tournament is picked. Without that, the
        // list showed every approved player in the organization regardless of whether
        // they had registered for the tournament being auctioned.
        $orgId = Auth::user()->organization_id;
        $query = Player::with(['registrations' => fn ($q) => $q->where('status', 'approved')->select('id', 'player_id', 'tournament_id', 'status')])
            ->whereHas('registrations', fn ($q) => $q->where('status', 'approved'))
            ->where(function ($q) {
                $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode');
            });
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        $availablePlayers = $query->orderBy('name')
            ->get(['id', 'name', 'organization_id'])
            ->map(fn (Player $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'organization_id' => $p->organization_id,
                // Tournaments this player is approved for, for the client-side filter.
                'tournament_ids' => $p->registrations->pluck('tournament_id')->unique()->values()->all(),
            ]);

        return view('backend.pages.auctions.create', compact('organizations', 'tournaments', 'availablePlayers'));
    }

    public function store(Request $request)
    {
        $this->authorize('auction.create');

        // Check auction access (bypass for Superadmin)
        if (! Auth::user()->hasRole('Superadmin')) {
            $organization = Auth::user()->organization_id
                ? \App\Models\Organization::find(Auth::user()->organization_id)
                : null;

            if ($organization && ! $organization->isAuctionEnabled()) {
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
            // Squad-reserve rule. The combination is additionally checked below: a
            // squad that costs more than the purse can never be assembled, so the
            // reserve would block every bid.
            'min_squad_size' => 'nullable|integer|min:1|max:50',
            'min_price_per_player' => 'nullable|numeric|min:0',
            // What amounts are called on every screen.
            'amount_unit' => 'nullable|in:points,coins,usd,custom',
            'amount_unit_label' => 'nullable|string|max:30|required_if:amount_unit,custom',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'bid_rules' => 'required|array|min:1',
            'bid_rules.*.from' => 'required|numeric|min:0',
            'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
            // Zero is the "maximum bid reached" sentinel at runtime — reject it here.
            'bid_rules.*.increment' => 'required|numeric|min:1',
            'bid_type' => 'required|in:open,closed',
            'bid_timer_seconds' => 'required|integer|min:5|max:300',
            'bid_timer_reset_seconds' => 'nullable|integer|min:5|max:300',
            // Timer behaviour: enforced server-side, and configurable at expiry.
            'timer_enabled' => 'nullable|boolean',
            'timer_expiry_action' => 'nullable|in:auto_sell,manual',
            // Closing calls ("going once, going twice") in the final seconds.
            'final_call_enabled' => 'nullable|boolean',
            'final_call_interval_seconds' => 'nullable|integer|min:1|max:30',
            // Player email: on/off, test mode, and when it goes out.
            'notifications_enabled' => 'nullable|boolean',
            'email_test_mode' => 'nullable|boolean',
            'email_dispatch' => 'nullable|in:immediate,deferred',
            // Optional quick-bid jump amounts offered alongside the increment ladder.
            'quick_bid_steps' => 'nullable|array',
            'quick_bid_steps.*' => 'nullable|numeric|min:0',
            'online_bid_limit_from' => 'nullable|numeric|min:0',
            // `gt` only applies when the lower bound was actually filled in — otherwise
            // setting just the offline threshold failed with a confusing message about a
            // field the operator left deliberately blank.
            'online_bid_limit_to' => array_filter([
                'nullable', 'numeric', 'min:0',
                $request->filled('online_bid_limit_from') ? 'gt:online_bid_limit_from' : null,
            ]),
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

        // A squad that costs more than the purse makes the reserve rule
        // unsatisfiable, which would block every bid.
        $this->assertSquadReserveIsSatisfiable($validated);

        if (! Auth::user()->hasRole('Superadmin')) {
            $validated['organization_id'] = Auth::user()->organization_id;
            if (! $validated['organization_id']) {
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
            if (! empty($validated['primary_color'])) {
                $brandingData['primary_color'] = $validated['primary_color'];
            }
            if (! empty($validated['secondary_color'])) {
                $brandingData['secondary_color'] = $validated['secondary_color'];
            }

            // Create the auction
            $auction = Auction::create(array_merge([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'min_squad_size' => $validated['min_squad_size'] ?? Auction::DEFAULT_MIN_SQUAD_SIZE,
                'min_price_per_player' => $validated['min_price_per_player'] ?? $validated['base_price'],
                'amount_unit' => $validated['amount_unit'] ?? Auction::UNIT_POINTS,
                'amount_unit_label' => $validated['amount_unit_label'] ?? null,
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'bid_rules' => $validated['bid_rules'],
                'bid_type' => $validated['bid_type'],
                'bid_timer_seconds' => $validated['bid_timer_seconds'],
                'bid_timer_reset_seconds' => $validated['bid_timer_reset_seconds'] ?? 15,
                'timer_enabled' => $request->boolean('timer_enabled', true),
                'timer_expiry_action' => $validated['timer_expiry_action'] ?? Auction::TIMER_MANUAL,
                'final_call_enabled' => $request->boolean('final_call_enabled', true),
                'final_call_interval_seconds' => $validated['final_call_interval_seconds'] ?? 3,
                'notifications_enabled' => $request->boolean('notifications_enabled', true),
                'email_test_mode' => $request->boolean('email_test_mode', false),
                'email_dispatch' => $validated['email_dispatch'] ?? Auction::EMAIL_DEFERRED,
                // Blank rows from the repeater are dropped; the model sorts and
                // de-duplicates what survives.
                'quick_bid_steps' => array_values(array_filter(
                    $validated['quick_bid_steps'] ?? [],
                    fn ($v) => is_numeric($v) && (float) $v > 0
                )),
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

        // Retained players are pre-kept by their team and managed entirely on the Pools
        // screen, so the wizard neither lists them nor submits them. They must therefore
        // be preserved explicitly — otherwise the rebuild below would delete a retained
        // row simply because it was absent from a payload that never carried it.
        $retainedPlayerIds = AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $preservePlayerIds = array_values(array_unique(array_merge($preservePlayerIds, $retainedPlayerIds)));

        // Drop only the waiting, non-retained players; pools themselves are matched by id
        // below so their base_price / category / capacity / status survive a wizard save.
        AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'waiting')
            ->where('is_retained', false)
            ->delete();

        // Remove only the pools the operator actually deleted.
        $submittedPoolIds = collect($pools)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->all();

        AuctionPool::where('auction_id', $auction->id)
            ->when($submittedPoolIds, fn ($q) => $q->whereNotIn('id', $submittedPoolIds))
            ->delete();

        $this->buildPoolsFromData($auction, $pools, $preservePlayerIds);

        // An actioned player whose pool was deleted keeps a stale lot_number pointing
        // at nothing (the FK is nullOnDelete), which can collide with a freshly drawn
        // lot. Clear it.
        AuctionPlayer::where('auction_id', $auction->id)
            ->whereNull('auction_pool_id')
            ->whereNotNull('lot_number')
            ->update(['lot_number' => null]);
    }

    /**
     * Shared pool/player creation from the wizard's pool-builder JSON. Org-isolates
     * players, skips any id in $skipPlayerIds (already-actioned), and assigns
     * lot_number per each pool's order mode.
     *
     * @param  array<int, array{id?:mixed,name?:string,capacity?:mixed,base_price?:mixed,category?:mixed,order_mode?:string,players?:array}>  $pools
     * @param  array<int, int>  $skipPlayerIds
     * @param  array<int, mixed>  $retainedPrices  player_id => retained_price, carried across a rebuild
     * @param  array<int, mixed>  $retainedTeams   player_id => team_id, carried across a rebuild
     */
    protected function buildPoolsFromData(
        Auction $auction,
        array $pools,
        array $skipPlayerIds = [],
        array $retainedPrices = [],
        array $retainedTeams = []
    ): void {
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
            $attributes = [
                'organization_id' => $auction->organization_id,
                'name' => trim((string) ($poolData['name'] ?? 'Pool')) ?: 'Pool',
                'order_mode' => in_array($mode, ['sequential', 'random', 'odd_even', 'manual'], true) ? $mode : 'sequential',
                'sequence' => $sequence + 1,
            ];

            // capacity / base_price / category are also editable on the pools screen, so
            // only overwrite them when the payload actually carries the key. A wizard
            // save that doesn't mention them must leave them as configured — it used to
            // reset all three to null on every save.
            foreach (['capacity', 'base_price', 'category'] as $field) {
                if (! array_key_exists($field, $poolData)) {
                    continue;
                }

                $value = $poolData[$field];
                $attributes[$field] = match ($field) {
                    'capacity' => is_numeric($value) ? (int) $value : null,
                    'base_price' => is_numeric($value) ? $value : null,
                    default => ($value === '' || $value === null) ? null : $value,
                };
            }

            // Match an existing pool by id so its status, usage counters and any
            // pool-screen settings survive; only fall back to creating when the
            // operator added a new pool.
            $poolId = is_numeric($poolData['id'] ?? null) ? (int) $poolData['id'] : null;
            $pool = $poolId
                ? AuctionPool::where('auction_id', $auction->id)->find($poolId)
                : null;

            if ($pool) {
                $pool->update($attributes);
            } else {
                $pool = AuctionPool::create($attributes + ['auction_id' => $auction->id]);
            }

            foreach (array_values($players) as $i => $pl) {
                $playerId = (int) ($pl['id'] ?? 0);
                if (! $playerId) {
                    continue;
                }
                // Price resolution: per-player → pool → auction.
                $base = $poolService->resolveBasePrice($auction, $pool, $pl['base_price'] ?? null);
                $isRetained = isset($retainedIds[$playerId]);

                $row = [
                    'auction_pool_id' => $pool->id,
                    'organization_id' => $auction->organization_id,
                    'base_price' => $base,
                    'current_price' => $base,
                    'starting_price' => $base,
                    'lot_number' => $isRetained ? null : ($i + 1), // retained players have no draw slot
                    'status' => 'waiting',
                    'is_retained' => $isRetained,
                ];

                // Carry a retained player's price and team across the rebuild.
                if ($isRetained) {
                    if (array_key_exists($playerId, $retainedPrices) && $retainedPrices[$playerId] !== null) {
                        $row['retained_price'] = $retainedPrices[$playerId];
                    }
                    if (! empty($retainedTeams[$playerId])) {
                        $row['team_id'] = $retainedTeams[$playerId];
                    }
                }

                AuctionPlayer::updateOrCreate(
                    ['auction_id' => $auction->id, 'player_id' => $playerId],
                    $row
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
            'auctionPlayers.liveBids.team',
            'pools.players.player',
            'pools.players.soldToTeam',
        ]);

        // Retained players are pre-kept by their team, not auctioned, so they have no
        // place in this pool listing — they are managed on the Pools screen.
        $auction->setRelation(
            'auctionPlayers',
            $auction->auctionPlayers->reject(fn ($ap) => (bool) $ap->is_retained)->values()
        );

        // For non-admin users, filter to only show players sold to their team
        $userTeam = null;
        if (! $isAdmin) {
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
            'auction' => $auction,
            'teams' => $teams,
            'bidRules' => $bidRules,
            'isAdmin' => $isAdmin,
            'userTeam' => $userTeam,
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
            'auctionPlayers.soldToTeam',
        ]);

        // Start query on auctionPlayers
        $query = $auction->auctionPlayers()->with([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'soldToTeam',
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
            'playerID' => 'required|integer|exists:auction_players,id',
            'teamId' => 'nullable|integer|exists:actual_teams,id',
            // Index into the auction's configured quick-bid steps. An index rather than
            // an amount, so a client can never name its own jump size.
            'stepIndex' => 'nullable|integer|min:0',
        ]);

        $auction = Auction::findOrFail($data['auctionId']);

        // No bids while the auction is paused.
        if ($auction->status === 'paused') {
            return response()->json(['success' => false, 'message' => 'The auction is paused. Resume it to add bids.'], 423);
        }

        $increments = app(BidIncrementService::class);
        $pools = app(AuctionPoolService::class);
        $undo = app(AuctionUndoService::class);

        try {
            $result = DB::transaction(function () use ($data, $auction, $increments, $pools, $undo) {
                $player = AuctionPlayer::where('auction_id', $auction->id)
                    ->lockForUpdate()
                    ->findOrFail($data['playerID']);

                // Prevent consecutive bids by the same team
                if ($data['teamId'] && $player->current_bid_team_id == $data['teamId']) {
                    throw new \Exception('This team is already the highest bidder.');
                }

                // The clock is enforced here too, so the organizer's own buttons obey
                // the same rule the team managers do.
                if ($auction->timerStateFor($player)['expired']) {
                    throw new \Exception('Time is up for this player. Bidding is closed — use SELL or PASS.');
                }

                $current = (float) $player->current_price;

                // A quick-step jump replaces the standard increment for this one bid.
                $steps = $auction->quickBidSteps();
                $stepIndex = $data['stepIndex'] ?? null;

                if ($stepIndex !== null) {
                    if (! array_key_exists($stepIndex, $steps)) {
                        throw new \Exception('That quick-bid step is not configured for this auction.');
                    }
                    $increment = $steps[$stepIndex];
                } else {
                    $increment = $increments->incrementFor($auction, $current);
                }

                if ($increment <= 0) {
                    throw new \Exception('Maximum bid reached.');
                }

                $newPrice = $current + $increment;

                // Squad-reserve rule. The organizer's manual bid path previously
                // had no budget check of any kind, so a team could be bid past
                // its cap and only be blocked at SELL.
                if ($data['teamId']) {
                    $team = ActualTeam::find($data['teamId']);
                    if (! $pools->canAffordWithReserve($auction, (int) $data['teamId'], $newPrice)) {
                        throw new \Exception(
                            $pools->reserveBlockedMessage($auction, (int) $data['teamId'], $newPrice, $team?->name)
                        );
                    }
                }

                $previousPrice = $current;
                $previousTeamId = $player->current_bid_team_id;

                $player->current_price = $newPrice;
                $player->final_price = $newPrice;
                $player->current_bid_team_id = $data['teamId'] ?? null;
                $player->save();

                // Determine bid source based on current auction mode
                $bidSource = $auction->isOfflineMode() ? 'offline' : 'online';

                // Append-only: every raise is its own row so Undo has a stack to
                // walk back through.
                $bid = AuctionBid::create([
                    'auction_id' => $auction->id,
                    'auction_player_id' => $player->id,
                    'team_id' => $data['teamId'] ?? null,
                    'user_id' => auth()->id(),
                    'player_id' => $player->player_id,
                    'amount' => $newPrice,
                    'bid_source' => $bidSource,
                ]);

                $teamName = isset($team) ? ($team?->name ?? null) : null;

                $undo->record(
                    $auction,
                    AuctionActionLog::ACTION_BID,
                    $player,
                    [
                        'bid_id' => $bid->id,
                        'amount' => $newPrice,
                        'team_id' => $data['teamId'] ?? null,
                        'team_name' => $teamName,
                        'previous_price' => $previousPrice,
                        'previous_team_id' => $previousTeamId,
                    ],
                    sprintf('Bid %s by %s', format_points($newPrice), $teamName ?? 'unknown team')
                );

                // A successful bid restarts the clock (at bid_timer_reset_seconds).
                $auction->update(['timer_started_at' => now()]);

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
            && ! $auction->mode_manually_overridden
            && $auction->bid_type === 'open'
            && $newPrice >= (float) $auction->closed_bid_starts_at) {
            $auction->update(['bid_type' => 'closed']);
        }

        // Auto-transition to offline if price exceeds online limit
        $auction = $auction->fresh();
        if ($auction->hasOnlineOfflineMode()
            && ! $auction->mode_manually_overridden
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
            'success' => true,
            'current_price' => $newPrice,
            'increment_used' => $increment,
            'open_bid_mode' => $auction->fresh()->open_bid_mode,
        ]);
    }

    /**
     * Step the current price back down — i.e. retract the most recent raise.
     *
     * This delegates to the undo stack rather than computing a lower price of its
     * own. The old implementation dropped the price by one increment and then
     * wrote a *new* bid row at that lower amount, which polluted the bid history
     * and inflated every budget total derived from auction_bids. Retracting the
     * actual bid is both correct and what the operator means when they click it.
     */
    public function decreaseBid(Request $request, AuctionUndoService $undo)
    {
        $data = $request->validate([
            'auctionId' => 'required|integer|exists:auctions,id',
            'playerID' => 'required|integer|exists:auction_players,id',
            'teamId' => 'nullable|integer|exists:actual_teams,id',
        ]);

        $auction = Auction::findOrFail($data['auctionId']);
        $player = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($data['playerID']);

        $result = $undo->undoLast($auction);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Cannot decrease further.',
                'current_price' => (float) $player->current_price,
            ], 400);
        }

        $player = $player->fresh();
        $player->load([
            'player.player_type',
            'player.batting_profile',
            'player.bowling_profile',
        ]);

        broadcast(new PlayerOnBidEvent($player, $player->currentBidTeam))->toOthers();

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'current_price' => (float) $player->current_price,
            'current_bid_team_id' => $player->current_bid_team_id,
        ]);
    }

    public function edit(Auction $auction)
    {
        $organizations = Organization::orderBy('name')->get();
        // Auctions attach only to auction-type tournaments (budget/pools apply there).
        $tournaments = Tournament::forUser(auth()->user())->where('type', 'auction')->orderBy('name')->get();

        // Load the players currently in the auction, its pools, tournament, and budgets.
        $auction->load(['auctionPlayers.player', 'pools.players.player', 'tournament', 'teamBudgets']);
        $auctionPlayerIds = $auction->auctionPlayers->pluck('player.id')->toArray();

        // Available = players with an APPROVED registration for this auction's
        // tournament who are not already in it.
        //
        // Retained players are deliberately excluded: they are pre-kept by their team,
        // not drawn for bidding, and their retention price is managed on the Pools
        // screen (which can also merge them into the run). Listing them here invited
        // them into the biddable pool by mistake — and matches how Create behaves.
        $tournamentId = $auction->tournament_id;
        $availablePlayers = Player::whereHas('registrations', fn ($q) => $q->where('tournament_id', $tournamentId)->where('status', 'approved'))
            ->whereNotIn('id', $auctionPlayerIds)
            ->where(function ($q) {
                $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode');
            })
            ->when($auction->organization_id, function ($query) use ($auction) {
                $query->where('organization_id', $auction->organization_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id', 'player_mode'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'organization_id' => $p->organization_id,
                'retained' => false,
            ]);

        // Existing pools (sequence order) with their players (lot order) for the wizard.
        $existingPools = $auction->pools->sortBy('sequence')->values()->map(function ($pool) {
            return [
                'id' => $pool->id,
                'name' => $pool->name,
                'capacity' => $pool->capacity,
                'order_mode' => $pool->order_mode,
                // Pool-level base price / category, so a wizard save round-trips them
                // instead of resetting them to null.
                'base_price' => $pool->base_price,
                'category' => $pool->category,
                // Retained players are excluded from the wizard entirely — they belong to
                // the Pools screen, and syncAuctionPools() preserves their rows.
                'players' => $pool->players->reject(fn ($ap) => (bool) $ap->is_retained)
                    ->sortBy('lot_number')->values()->map(fn ($ap) => [
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
            // Retained players stay out of the wizard entirely.
            ->reject(fn ($ap) => (bool) $ap->is_retained)
            ->map(fn ($ap) => [
                'id' => $ap->player_id,
                'name' => $ap->player->name ?? ('Player #' . $ap->player_id),
                'base_price' => $ap->base_price,
                'org' => $ap->organization_id,
                'retained' => false,
            ])->values()->all();

        // Teams in this auction's tournament + their existing per-team budgets.
        $budgetTeams = $auction->tournament_id
            ? ActualTeam::forTournament($auction->tournament_id)->orderBy('name')->get()
            : collect();
        $teamBudgets = $auction->teamBudgets->keyBy('actual_team_id'); // actual_team_id => AuctionTeamBudget

        return view('backend.pages.auctions.edit', compact(
            'auction',
            'organizations',
            'tournaments',
            'availablePlayers',
            'existingPools',
            'unpooled',
            'budgetTeams',
            'teamBudgets'
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
            'status' => 'closed',
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

        $messages = [
            'tournament_id.required' => 'You must select a tournament for the auction.',
            'bid_rules.*.to.gt' => 'The "To" value in a bid rule must be greater than the "From" value.',
            'bid_rules.*.increment.min' => 'A bid increment must be greater than zero, otherwise bidding cannot progress past that band.',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Nullable, not required: legacy auctions have organization_id = NULL and
            // post an empty hidden input, which used to fail validation and abort the
            // whole save with no visible error. The org is re-derived from the
            // tournament below regardless.
            'organization_id' => 'nullable|exists:organizations,id',
            'tournament_id' => 'required|exists:tournaments,id',
            'status' => 'required|string|in:scheduled,running,completed',
            'max_budget_per_team' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            // Squad-reserve rule. The combination is additionally checked below: a
            // squad that costs more than the purse can never be assembled, so the
            // reserve would block every bid.
            'min_squad_size' => 'nullable|integer|min:1|max:50',
            'min_price_per_player' => 'nullable|numeric|min:0',
            // What amounts are called on every screen.
            'amount_unit' => 'nullable|in:points,coins,usd,custom',
            'amount_unit_label' => 'nullable|string|max:30|required_if:amount_unit,custom',
            'bid_rules' => 'required|array|min:1',
            'bid_rules.*.from' => 'required|numeric|min:0',
            'bid_rules.*.to' => 'required|numeric|gt:bid_rules.*.from',
            // An increment of 0 is the "maximum bid reached" sentinel at runtime, so
            // reject it at save time rather than surfacing a confusing live error.
            'bid_rules.*.increment' => 'required|numeric|min:1',
            'bid_type' => 'required|in:open,closed',
            'bid_timer_seconds' => 'required|integer|min:5|max:300',
            'bid_timer_reset_seconds' => 'nullable|integer|min:5|max:300',
            // Timer behaviour: enforced server-side, and configurable at expiry.
            'timer_enabled' => 'nullable|boolean',
            'timer_expiry_action' => 'nullable|in:auto_sell,manual',
            // Closing calls ("going once, going twice") in the final seconds.
            'final_call_enabled' => 'nullable|boolean',
            'final_call_interval_seconds' => 'nullable|integer|min:1|max:30',
            // Player email: on/off, test mode, and when it goes out.
            'notifications_enabled' => 'nullable|boolean',
            'email_test_mode' => 'nullable|boolean',
            'email_dispatch' => 'nullable|in:immediate,deferred',
            // Optional quick-bid jump amounts offered alongside the increment ladder.
            'quick_bid_steps' => 'nullable|array',
            'quick_bid_steps.*' => 'nullable|numeric|min:0',
            'online_bid_limit_from' => 'nullable|numeric|min:0',
            // `gt` only applies when the lower bound was actually filled in — otherwise
            // setting just the offline threshold failed with a confusing message about a
            // field the operator left deliberately blank.
            'online_bid_limit_to' => array_filter([
                'nullable', 'numeric', 'min:0',
                $request->filled('online_bid_limit_from') ? 'gt:online_bid_limit_from' : null,
            ]),
            'closed_bid_starts_at' => 'nullable|numeric|min:0',
            // Branding
            'background_image' => 'nullable|image|max:5120',
            'auction_logo' => 'nullable|image|max:5120',
            'waiting_background_image' => 'nullable|image|max:5120',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            // Pool builder (same as create); when present it drives the player layout.
            'pools' => 'nullable|string',
            // Per-team budget overrides (blank = uniform cap).
            'team_budgets' => 'nullable|array',
            'team_budgets.*' => 'nullable|numeric|min:0',
        ], $messages);

        // A squad that costs more than the purse makes the reserve rule
        // unsatisfiable, which would block every bid.
        $this->assertSquadReserveIsSatisfiable($validated);

        // Keep the auction's org aligned to its tournament so player isolation never
        // mismatches (prevents the pool sync from dropping legitimate players).
        $validated['organization_id'] = Tournament::whereKey($validated['tournament_id'])->value('organization_id')
            ?: ($validated['organization_id'] ?? $auction->organization_id);

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
            // !empty(), not has(): <input type="color"> always posts and defaults to
            // #000000, so has() stamped black onto every auction that had no colour
            // set and made clearing a colour impossible.
            if (! empty($validated['primary_color'])) {
                $brandingData['primary_color'] = $validated['primary_color'];
            }
            if (! empty($validated['secondary_color'])) {
                $brandingData['secondary_color'] = $validated['secondary_color'];
            }

            $auction->update(array_merge([
                'name' => $validated['name'],
                'organization_id' => $validated['organization_id'],
                'tournament_id' => $validated['tournament_id'],
                'status' => $validated['status'],
                'max_budget_per_team' => $validated['max_budget_per_team'],
                'base_price' => $validated['base_price'],
                'min_squad_size' => $validated['min_squad_size'] ?? $auction->min_squad_size ?? Auction::DEFAULT_MIN_SQUAD_SIZE,
                'min_price_per_player' => $validated['min_price_per_player'] ?? $auction->min_price_per_player,
                'amount_unit' => $validated['amount_unit'] ?? $auction->amount_unit ?? Auction::UNIT_POINTS,
                'amount_unit_label' => $validated['amount_unit_label'] ?? null,
                'bid_rules' => $validated['bid_rules'],
                'bid_type' => $validated['bid_type'],
                'bid_timer_seconds' => $validated['bid_timer_seconds'],
                'bid_timer_reset_seconds' => $validated['bid_timer_reset_seconds'] ?? 15,
                'timer_enabled' => $request->boolean('timer_enabled', true),
                'timer_expiry_action' => $validated['timer_expiry_action'] ?? Auction::TIMER_MANUAL,
                'final_call_enabled' => $request->boolean('final_call_enabled', true),
                'final_call_interval_seconds' => $validated['final_call_interval_seconds'] ?? 3,
                'notifications_enabled' => $request->boolean('notifications_enabled', true),
                'email_test_mode' => $request->boolean('email_test_mode', false),
                'email_dispatch' => $validated['email_dispatch'] ?? Auction::EMAIL_DEFERRED,
                // Blank rows from the repeater are dropped; the model sorts and
                // de-duplicates what survives.
                'quick_bid_steps' => array_values(array_filter(
                    $validated['quick_bid_steps'] ?? [],
                    fn ($v) => is_numeric($v) && (float) $v > 0
                )),
                'online_bid_limit_from' => $validated['online_bid_limit_from'] ?? null,
                'online_bid_limit_to' => $validated['online_bid_limit_to'] ?? null,
                'closed_bid_starts_at' => $validated['closed_bid_starts_at'] ?? null,
            ], $brandingData));

            // Preferred path: the pool-builder JSON (same as create) drives the player
            // layout. Rebuilds the "waiting" players/pools/lots; never touches players
            // already sold/on-auction/closed.
            // An empty/absent `pools` payload means "the wizard sent no pool layout",
            // which must leave the existing layout alone. (The old else-branch here
            // read player_base_prices, inputs the current form does not render.)
            $pools = json_decode($validated['pools'] ?? '', true);
            if (is_array($pools) && count($pools)) {
                $this->syncAuctionPools($auction, $pools);
            }

            // Per-team budget overrides (blank clears the override → uniform cap applies).
            // Keys are request-supplied, so only teams that actually belong to this
            // auction's tournament may be written — otherwise any actual_team_id could
            // be injected, including another tournament's or org's team.
            if (is_array($request->input('team_budgets'))) {
                $eligibleTeamIds = ActualTeam::forTournament($auction->tournament_id)
                    ->pluck('id')
                    ->flip();

                foreach ($request->input('team_budgets') as $teamId => $budget) {
                    if (! $eligibleTeamIds->has((int) $teamId)) {
                        continue;
                    }
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

    /**
     * Send the auction's held emails now.
     *
     * The outbox lives in the database, so if no queue worker is running the mail is not
     * lost — it simply waits here until someone presses this. That is deliberate: an
     * unattended queue must not silently swallow a player's welcome card.
     */
    /**
     * The auction's email outbox.
     *
     * Test mode is only useful if you can read back what a real run would have sent, so
     * every raised message is listed here with its status and, on failure, the reason.
     */
    public function emailOutbox(Auction $auction, Request $request)
    {
        $this->authorize('auction.edit');

        $status = $request->query('status');
        $valid = [
            \App\Models\AuctionPendingEmail::STATUS_PENDING,
            \App\Models\AuctionPendingEmail::STATUS_SENT,
            \App\Models\AuctionPendingEmail::STATUS_SKIPPED,
            \App\Models\AuctionPendingEmail::STATUS_FAILED,
        ];

        $query = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)
            ->with(['player.user', 'team']);

        if (in_array($status, $valid, true)) {
            $query->where('status', $status);
        }

        $emails = $query->orderByDesc('id')->paginate(50)->withQueryString();

        $counts = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('backend.pages.auctions.emails', [
            'auction' => $auction,
            'emails' => $emails,
            'counts' => $counts,
            'activeStatus' => in_array($status, $valid, true) ? $status : null,
            'breadcrumbs' => [
                'title' => 'Email Outbox',
                'items' => [
                    ['label' => 'Auctions', 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    /**
     * Put failed (or test-mode skipped) messages back in the queue.
     *
     * Resending is a matter of flipping them to pending — `flush()` is the only thing that
     * delivers, so there is one send path and no second implementation to keep in step.
     */
    public function retryEmails(Auction $auction, Request $request)
    {
        $this->authorize('auction.edit');

        $scope = $request->input('scope') === 'skipped'
            ? \App\Models\AuctionPendingEmail::STATUS_SKIPPED
            : \App\Models\AuctionPendingEmail::STATUS_FAILED;

        $reset = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)
            ->where('status', $scope)
            ->update([
                'status' => \App\Models\AuctionPendingEmail::STATUS_PENDING,
                'error' => null,
            ]);

        if ($reset === 0) {
            return back()->with('error', "There are no {$scope} emails to requeue.");
        }

        // Requeuing a test-mode message and leaving test mode on would only skip it again.
        $note = $this->mailServiceIsTestMode($auction)
            ? ' Test mode is still on, so they will be skipped again until you turn it off.'
            : ' Press "Send now" to release them.';

        return back()->with('success', "{$reset} email(s) moved back to the queue." . $note);
    }

    private function mailServiceIsTestMode(Auction $auction): bool
    {
        return (bool) ($auction->email_test_mode ?? false);
    }

    public function flushEmails(Auction $auction, \App\Services\Auction\AuctionMailService $mail)
    {
        $this->authorize('auction.edit');

        if (! $mail->notificationsEnabled($auction)) {
            return back()->with('error', 'Player emails are switched off for this auction.');
        }

        $pending = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count();
        if ($pending === 0) {
            return back()->with('error', 'There is nothing waiting to be sent.');
        }

        $result = $mail->flush($auction);

        if ($mail->isTestMode($auction)) {
            return back()->with('success', sprintf(
                'Test mode: %d email(s) marked as skipped — nothing was sent.',
                $result['skipped']
            ));
        }

        return back()->with('success', sprintf(
            '%d email(s) sent.%s%s',
            $result['sent'],
            $result['failed'] > 0 ? sprintf(' %d failed.', $result['failed']) : '',
            // Sending is chunked, so a large batch needs more than one pass.
            $result['remaining'] > 0 ? sprintf(' %d still waiting — press again to continue.', $result['remaining']) : ''
        ));
    }

    public function removeBrandingImage(Request $request, Auction $auction)
    {
        $this->authorize('auction.edit');

        $field = $request->input('field');
        $allowed = ['background_image', 'auction_logo', 'waiting_background_image'];

        if (! in_array($field, $allowed)) {
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
        // Deleting an auction had no authorization check at all.
        $this->authorize('auction.delete');

        $auction->delete();
        return redirect()->route('admin.auctions.index')->with('success', 'Auction deleted successfully.');
    }

    public function clearPool(Auction $auction)
    {
        // `auction.edit`, not the legacy duplicate `auctions.edit` permission group —
        // the latter is not granted to any current role, so this check never actually
        // matched what the rest of the module uses.
        $this->authorize('auction.edit');

        // Use the relationship to delete all associated auction players.
        $auction->auctionPlayers()->delete();

        return back()->with('success', 'The entire player pool has been cleared.');
    }

    public function removePlayer(AuctionPlayer $auctionPlayer)
    {
        $this->authorize('auction.edit');

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
            'message' => 'Player removed from pool.',
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

        // Use final_price from request, or fallback to current/base price
        $newPrice = (float) ($request->final_price ?? ($auctionPlayer->current_price ?? $auctionPlayer->base_price));

        // Budget + squad-reserve check.
        $poolService = app(AuctionPoolService::class);
        if (! $poolService->canAffordWithReserve($auction, $team->id, $newPrice)) {
            return response()->json([
                'error' => $poolService->reserveBlockedMessage($auction, $team->id, $newPrice, $team->name),
            ], 400);
        }

        // Audit bid for the manual assignment.
        $auditBid = AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer->id,
            'player_id' => $auctionPlayer->player_id,
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'amount' => $newPrice,
            'bid_source' => 'offline',
        ]);

        // Same shared sale path as SELL and sealed-bid award — it writes the roster
        // pivot, assigns (rather than replaces) the Player role, and sends the
        // welcome card. This used to hand-roll all of it, including a
        // syncRoles(['Player']) that stripped a Team Manager of their manager role.
        $sales = app(\App\Services\Auction\AuctionSaleService::class);
        $snapshot = $sales->applySale($auctionPlayer, $team, $newPrice);

        app(AuctionUndoService::class)->record(
            $auction,
            AuctionActionLog::ACTION_SELL,
            $auctionPlayer,
            $snapshot + [
                'amount' => $newPrice,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'audit_bid_id' => $auditBid->id,
            ],
            sprintf('Assigned to %s for %s', $team->name, format_points($newPrice))
        );

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
            'auctionPlayers.liveBids' => function ($q) {
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
            $bids = $ap->liveBids->sortBy('created_at')->values();
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
        $totalBids = $auction->auctionPlayers->sum(fn ($ap) => $ap->liveBids->count());
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
            $teamBidsCount = $auction->auctionPlayers->sum(fn ($ap) => $ap->liveBids->where('team_id', $team->id)->count());
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
        } elseif ($request->status == 'sold') {
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
            'status' => $auctionPlayer->status,
        ]);
    }
}
