<?php

namespace App\Http\Controllers\Backend;

use App\Events\PlayerOnBidEvent;
use App\Jobs\RenderAuctionCards;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\ActualTeamUser;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionCardExport;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use Illuminate\Http\JsonResponse;
use App\Models\AuctionActionLog;
use App\Services\Auction\AuctionCardRenderer;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\BidIncrementService;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    /**
     * Refuse a bid ladder that cannot raise the opening price.
     *
     * `incrementFor()` matches the band containing the current price, then falls back to the
     * next band ABOVE it. If the base price sits above every band, neither finds anything and
     * the increment is 0 — which the panel reports as "Maximum bid reached." before a single
     * bid has been placed, and the player can never be sold.
     *
     * This is exactly the failure that only shows up mid-event with a player on the block, so
     * it is caught at save time with the arithmetic spelled out. A ladder that stops *below*
     * the base price is always a mistake: no configuration makes it work.
     */
    protected function assertBidLadderCoversBasePrice(array $validated): void
    {
        $base = $validated['base_price'] ?? null;
        $rules = $validated['bid_rules'] ?? null;

        if ($base === null || ! is_array($rules) || $rules === []) {
            return;
        }

        $tops = [];
        foreach ($rules as $rule) {
            // A band with no increment cannot raise anything, so it does not count as cover.
            if (! is_array($rule) || (float) ($rule['increment'] ?? 0) <= 0) {
                continue;
            }
            $tops[] = (float) ($rule['to'] ?? 0);
        }

        if ($tops === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bid_rules' => 'At least one bid rule needs an increment above zero, '
                    . 'or no bid can ever be placed.',
            ]);
        }

        /*
         * A base price above every band used to be fatal: no rule contained it, so the
         * increment was 0 and the panel reported "Maximum bid reached." before anybody had
         * bid. The top band now keeps applying above its own ceiling, so this is no longer
         * a dead end and blocking the save would refuse a configuration that works.
         *
         * The check above stays, because it is still true: with no rule carrying a positive
         * increment there is nothing to raise by at any price.
         */
    }

    /**
     * The bidding-mode half of a create/update payload.
     *
     * Online or offline is a decision about the WHOLE auction — who enters the bids — and
     * until now it could not be stated at all: `open_bid_mode` appeared nowhere in either
     * wizard and the migration defaults it to `online`, so every auction was born online and
     * an offline room had to be set up by pressing the panel's Offline toggle each session.
     *
     * Choosing offline also sets `mode_manually_overridden`, which has always meant "a human
     * chose this mode; do not auto-change it". Declaring it at creation is that same
     * statement made earlier, and it stops the `online_bid_limit_to` price rule from quietly
     * moving the mode later. Choosing ONLINE deliberately leaves the flag alone, so that
     * price rule keeps working for the auctions it is meant for.
     *
     * Returns an empty array when the key is absent, so an edit that does not mention the
     * mode leaves it exactly as it was — never `?? $auction->open_bid_mode` written
     * unconditionally, which is the preserve-on-absent trap documented on update().
     *
     * @return array<string, mixed>
     */
    protected function biddingModeData(array $validated): array
    {
        $mode = $validated['open_bid_mode'] ?? null;

        if ($mode === null) {
            return [];
        }

        $data = ['open_bid_mode' => $mode];

        if ($mode === 'offline') {
            $data['mode_manually_overridden'] = true;
        }

        return $data;
    }

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

        $displayTemplates = $this->selectableTemplates(\App\Models\AuctionTemplate::TYPE_LIVE_DISPLAY);
        $tickerTemplates = $this->selectableTemplates(\App\Models\AuctionTemplate::TYPE_TICKER);

        return view('backend.pages.auctions.create', compact(
            'organizations',
            'tournaments',
            'availablePlayers',
            'displayTemplates',
            'tickerTemplates'
        ));
    }

    /**
     * Templates this user may pick for a given screen: their own organization's, plus the
     * shared globals. Used by both wizards, so Create and Edit can never offer different
     * lists — Create offered none at all, which is why an auction created here always fell
     * back to the default wall however many templates existed.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\AuctionTemplate>
     */
    protected function selectableTemplates(string $type)
    {
        return \App\Models\AuctionTemplate::query()
            ->visibleTo(Auth::user())
            // The wall accepts player_card as well — same canvas, same element set.
            ->whereIn('type', \App\Models\AuctionTemplate::acceptableTypes($type))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'render_mode']);
    }

    /**
     * Refuse a sealed-bid configuration in which no legal bid could ever be placed.
     *
     * Each of these produces an auction that looks configured and then quietly fails at
     * the worst possible moment — mid-event, with a player on the block — so they are
     * caught at save time with the arithmetic spelled out.
     *
     * No-ops entirely when no sealed threshold is set.
     */
    protected function assertClosedBidRuleIsSatisfiable(array $validated): void
    {
        $threshold = $validated['closed_bid_starts_at'] ?? null;

        if ($threshold === null || $threshold === '') {
            return;
        }

        $threshold = (float) $threshold;
        $budget = (float) ($validated['max_budget_per_team'] ?? 0);
        $step = (float) ($validated['closed_bid_step'] ?? Auction::DEFAULT_CLOSED_BID_STEP);
        $pct = (float) ($validated['closed_bid_max_pct_of_budget'] ?? Auction::DEFAULT_CLOSED_BID_MAX_PCT);

        // 1. The opening amount must itself sit on the grid, or there is no legal bid at
        //    the floor. Integer cents, for the reason given in BidIncrementService.
        $stepCents = (int) round($step * 100);
        $thresholdCents = (int) round($threshold * 100);

        if ($stepCents > 0 && $thresholdCents % $stepCents !== 0) {
            $below = intdiv($thresholdCents, $stepCents) * $stepCents / 100;

            throw \Illuminate\Validation\ValidationException::withMessages([
                'closed_bid_starts_at' => sprintf(
                    'A sealed round opening at %s is not a multiple of the %s bid step, so no team could bid the opening amount. Use %s or %s.',
                    format_points($threshold),
                    format_points($step),
                    format_points($below),
                    format_points($below + $step)
                ),
            ]);
        }

        // 2. The per-player cap must be able to reach the floor, or every sealed round
        //    ends with nobody having been allowed to enter it.
        $cap = $budget * $pct / 100;

        if ($budget > 0 && $threshold > $cap) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'closed_bid_max_pct_of_budget' => sprintf(
                    'A sealed round opens at %s, but a per-player cap of %s%% of a %s budget is %s — no team could bid the opening amount. Raise the cap, raise the budget, or lower the sealed threshold.',
                    format_points($threshold),
                    rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
                    format_points($budget),
                    format_points($cap)
                ),
            ]);
        }

        // 3. The squad reserve must leave room for the floor. This is the one most likely
        //    to bite in practice, because min_squad_size defaults to 11.
        $squad = (int) ($validated['min_squad_size'] ?? Auction::DEFAULT_MIN_SQUAD_SIZE);
        $perPlace = (float) ($validated['min_price_per_player'] ?? $validated['base_price'] ?? 0);
        $reserve = max(0, $squad - 1) * $perPlace;

        if ($budget > 0 && $threshold > $budget - $reserve) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'closed_bid_starts_at' => sprintf(
                    'A sealed round opens at %s, but holding %s back for %d more squad place(s) leaves only %s of a %s budget — the reserve rule alone would block the opening amount.',
                    format_points($threshold),
                    format_points($reserve),
                    max(0, $squad - 1),
                    format_points(max(0, $budget - $reserve)),
                    format_points($budget)
                ),
            ]);
        }
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
            // Open-round per-player ceiling, as a share of a team's allocation. Blank means no
            // ceiling; 0 would mean "may not bid at all", so the floor is 1.
            'max_bid_pct_of_budget' => 'nullable|numeric|min:1|max:100',
            'auction_template_id' => 'nullable|exists:auction_templates,id',
            'ticker_template_id' => 'nullable|exists:auction_templates,id',
            'default_retained_value' => 'nullable|numeric|min:0',
            'expected_retained_per_team' => 'nullable|integer|min:0|max:50',
            // Sealed-round rules. A step of 0 is a configuration error, not "any amount
            // is legal", so it is refused here rather than defended against downstream.
            'closed_bid_step' => 'nullable|numeric|min:0.01',
            'closed_bid_max_pct_of_budget' => 'nullable|numeric|min:1|max:100',
            'closed_bid_max_rebid_rounds' => 'nullable|integer|min:0|max:5',
            'closed_bid_timer_seconds' => 'nullable|integer|min:5|max:600',
            'closed_bid_requires_acceptance' => 'nullable|boolean',
            'closed_bid_auto_rebid' => 'nullable|boolean',
            'closed_bid_tie_breaker' => 'nullable|in:lot,manual',
            'team_budgets' => 'nullable|array',
            'team_budgets.*' => 'nullable|numeric|min:0',
            // What amounts are called on every screen.
            'amount_unit' => 'nullable|in:points,coins,usd,custom',
            'amount_unit_label' => 'nullable|string|max:30|required_if:amount_unit,custom',
            'show_squad_values' => 'nullable|boolean',
            'show_acquisition_badge' => 'nullable|boolean',
            // Whether this auction ignores the tournament's squad rules — Auction::rule().
            'overrides_tournament_rules' => 'nullable|boolean',
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

            // Online or offline: who enters the bids for the whole auction. Nullable, not
            // required -- AuctionUpdatePoolsTest posts a minimal payload, and any newly
            // required field breaks every existing edit.
            'open_bid_mode' => 'nullable|in:online,offline',

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
        $this->assertClosedBidRuleIsSatisfiable($validated);
        $this->assertBidLadderCoversBasePrice($validated);

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
                'max_bid_pct_of_budget' => $validated['max_bid_pct_of_budget'] ?? null,
                'min_price_per_player' => $validated['min_price_per_player'] ?? $validated['base_price'],
                // `?? null` rather than a const: the accessors own the defaults, so a
                // blank field stays blank and stays clearable.
                'auction_template_id' => $validated['auction_template_id'] ?? null,
                'ticker_template_id' => $validated['ticker_template_id'] ?? null,
                /*
                 * Derived from Team Size, never taken from the request.
                 *
                 * The wizard used to ask for a minimum AND a maximum, and nothing in the auction
                 * used the difference — the only thing the pair did was refuse the whole save
                 * when they disagreed, which is how a maximum of 8 under a minimum of 11 silently
                 * rejected an entire form. One number now feeds both columns, so the reserve rule
                 * and the live screens keep reading the fields they always have and the two can
                 * never contradict each other again.
                 */
                'max_squad_size' => $validated['min_squad_size'] ?? Auction::DEFAULT_MIN_SQUAD_SIZE,
                'default_retained_value' => $validated['default_retained_value'] ?? null,
                'expected_retained_per_team' => $validated['expected_retained_per_team'] ?? null,
                'closed_bid_step' => $validated['closed_bid_step'] ?? null,
                'closed_bid_max_pct_of_budget' => $validated['closed_bid_max_pct_of_budget'] ?? null,
                'closed_bid_max_rebid_rounds' => $validated['closed_bid_max_rebid_rounds'] ?? null,
                'closed_bid_timer_seconds' => $validated['closed_bid_timer_seconds'] ?? null,
                'closed_bid_requires_acceptance' => $request->boolean('closed_bid_requires_acceptance', true),
                'closed_bid_auto_rebid' => $request->boolean('closed_bid_auto_rebid'),
                'closed_bid_tie_breaker' => $validated['closed_bid_tie_breaker'] ?? null,
                'amount_unit' => $validated['amount_unit'] ?? Auction::UNIT_POINTS,
                'amount_unit_label' => $validated['amount_unit_label'] ?? null,
                /*
                 * has() then boolean(), not boolean(default: true).
                 *
                 * An unticked checkbox posts nothing, and boolean($key, true) returns the
                 * default when the key is ABSENT — so the toggle could be switched on and never
                 * off. The edit form posts a hidden 0 alongside it so the key is always present
                 * and a tick is honoured either way; the create wizard renders no such field, so
                 * absence there correctly means "new auctions show values".
                 */
                'overrides_tournament_rules' => $request->boolean('overrides_tournament_rules'),
                'show_acquisition_badge' => $request->boolean('show_acquisition_badge', true),
                'show_squad_values' => $request->has('show_squad_values')
                    ? $request->boolean('show_squad_values')
                    : true,
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
            ], $brandingData, $this->biddingModeData($validated)));

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

            $this->syncTeamBudgets($auction, $request->input('team_budgets'));
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

        $poolService = app(AuctionPoolService::class);

        /*
         * Scoped to the AUCTION's retained players, not to this pool's.
         *
         * Retained rows are deliberately pool-less (see
         * AuctionPoolService::syncRetainedPlayers()) because a retained player is never bid
         * on. Looking for them inside a pool therefore found nothing and reported a silent
         * "merged 0". Merging is precisely the act of moving them INTO this pool, so the
         * pool is the destination here rather than the filter.
         */
        $query = AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            // Never rewrite a completed result on the strength of a stale flag.
            ->where('status', 'waiting');

        if (! empty($data['auction_player_ids'])) {
            $query->whereIn('id', $data['auction_player_ids']);
        }

        // The pool's price, not the retained row's — a retained row carries base_price 0
        // because it was never meant to be bid on, so merging without this put the player
        // on the block for nothing.
        $base = $poolService->resolveBasePrice($auction, $pool);

        $merged = $query->update([
            'is_retained' => false,
            'auction_pool_id' => $pool->id,
            'status' => 'waiting',
            'lot_number' => null,
            'base_price' => $base,
            'current_price' => $base,
            'starting_price' => $base,
            // The retention is off, so the team is no longer charged for them and no
            // longer holds them. Left set, these would keep implying a claim that the
            // budget arithmetic has already stopped honouring.
            'team_id' => null,
            'retained_price' => null,
        ]);

        // Slot the merged players into the pool's draw order.
        $poolService->generateLotNumbers($pool);

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

        /*
         * Retained players, read from the AUCTION rather than from inside a pool.
         *
         * Their rows are deliberately pool-less, so the per-pool "Retained (n)" section that
         * used to hold them is always empty now — which silently took the "Merge into
         * auction" control off the page with it. They belong to the auction, so they are
         * listed once, here.
         */
        $retainedPlayers = AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->where('status', 'waiting')
            ->with(['player:id,name', 'team:id,name'])
            ->get();

        /*
         * Approved players who are in no pool, and so are in no draw.
         *
         * This page accounted for the auction and stopped there: Sold, Unsold, Available and
         * Total Pool all describe rows that exist in `auction_players`, and a player nobody ever
         * assigned to a pool has no such row. Tournament 25 approved 378 players and the page
         * added up to 371 — 64 icon players plus a pool of 307 — with no indication that the
         * remaining 7 had been left out. They are visible one screen away, in Manage Pools'
         * "Unassigned players" panel, but only if you already suspected they were there.
         *
         * Same definition that panel uses (AuctionPoolController::index): approved for this
         * tournament, not retained, not already in a pool. Counted rather than listed — the
         * point here is to say a number is missing and where to go, not to duplicate the panel
         * that fixes it.
         */
        $unpooledCount = 0;

        if ($isAdmin && $auction->tournament_id) {
            $pooledPlayerIds = AuctionPlayer::where('auction_id', $auction->id)
                ->whereNotNull('auction_pool_id')
                ->pluck('player_id');

            $unpooledCount = Player::whereHas(
                'registrations',
                fn ($q) => $q->where('tournament_id', $auction->tournament_id)->where('status', 'approved')
            )
                ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
                ->whereNotIn('id', $pooledPlayerIds)
                ->where(fn ($q) => $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode'))
                ->count();
        }

        /*
         * The poster designs this auction can be exported onto, if any have been drawn.
         *
         * Empty is the normal state and the page says nothing when it is — an operator who has
         * never opened the designer should not be shown a picker with one option in it.
         */
        $posterTemplates = $auction->tournament_id
            ? TournamentTemplate::where('tournament_id', $auction->tournament_id)
                ->whereIn('type', [
                    TournamentTemplate::TYPE_AUCTION_POSTER,
                    TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
                ])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
            : collect();

        return view('backend.pages.auctions.show', [
            'auction' => $auction,
            'teams' => $teams,
            'bidRules' => $bidRules,
            'isAdmin' => $isAdmin,
            'userTeam' => $userTeam,
            'retainedPlayers' => $retainedPlayers,
            'posterTemplates' => $posterTemplates,
            'unpooledCount' => $unpooledCount,
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

                /*
                 * The clock binds the ROOM, not the person running it.
                 *
                 * This used to refuse an expired bid here as well, on the reasoning that the
                 * organizer's buttons should obey the same rule the team managers do. In a hall
                 * that is backwards: the countdown exists to stop teams stalling, and when it
                 * runs out the organizer is the one who has to put things right — record a bid
                 * that was called a second before the hammer, correct a mis-entry, take a raise
                 * from the floor. Refusing them left the only person who could fix it unable to
                 * act, with a player stuck on the block.
                 *
                 * The lock stays where it belongs: AuctionBiddingController::placeBid() still
                 * refuses a team's own bid after expiry, and the sealed round still refuses a
                 * late submission. Both of those are the room. This is the operator.
                 */
                $operator = auth()->user();
                $runsTheAuction = (bool) ($operator?->can('auction.control') || $operator?->can('auction.edit'));

                // Both permissions, because that is exactly the set this route admits — keying
                // the exemption off a narrower one would lock out an operator the router let in.
                if (! $runsTheAuction && $auction->timerStateFor($player)['expired']) {
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

                /*
                 * The opening bid is the base price itself.
                 *
                 * Adding an increment to it made the base a figure nobody ever paid — a
                 * 1,000,000 base opened at 1,100,000, so the number on the player's card, the
                 * poster and the wall was never a number a team could call. The first team takes
                 * the base; the ladder starts from there.
                 *
                 * A quick-step jump is still a deliberate jump on top of it: an organizer who
                 * picks +5M for the opening call means the base plus five, not five.
                 */
                $opening = $player->current_bid_team_id === null;

                if ($opening && $stepIndex === null) {
                    $newPrice = $current;
                } else {
                    if ($increment <= 0) {
                        // Names the real cause: a base price above the top band is not the same
                        // thing as a ladder that has been climbed to its end.
                        throw new \Exception($increments->noIncrementReason($auction, $current));
                    }

                    $newPrice = $current + $increment;
                }

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

                return ['newPrice' => $newPrice, 'increment' => $increment, 'player' => $player, 'bid_id' => $bid->id];
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

        // Same single rule as the team bid path, including opening the sealed round.
        $phase = $auction->applyAutoPhase((float) $newPrice);
        $auction = $auction->fresh();

        if ($phase['bid_type_changed']) {
            app(\App\Services\Auction\ClosedBidService::class)->openRoundFor($player->fresh(), $auction);
        }

        // Load relationships for frontend
        $player->load([
            'player.player_type',
            'player.batting_profile',
            'player.bowling_profile',
        ]);

        $team = ActualTeam::find($data['teamId'] ?? null);

        /*
         * Announced AFTER the response, not before it.
         *
         * Both of these are ShouldBroadcastNow, which calls Pusher inline — measured at ~1160ms
         * for the first (the TLS handshake, paid per request) plus ~290ms for the second. So a
         * raise made the organizer wait about a second and a half on a third party's network
         * before their own screen confirmed the bid, and held a PHP worker for all of it.
         *
         * The listening screens are not delayed by this: the same calls go out milliseconds
         * later. Only the person who acted stops waiting for them.
         */
        \App\Support\AfterResponse::run(function () use ($player, $team, $result) {
            broadcast(new PlayerOnBidEvent($player, $team))->toOthers();

            /*
             * The raise itself, for the screens that only need the new number.
             *
             * Not ->toOthers(): both panels bid through this endpoint, and the panel that did
             * not place the bid is precisely the one that needs telling. The listener's own
             * bid_id ordering makes a self-delivered frame a no-op.
             */
            \App\Events\BidRaised::announce($player, (int) $result['bid_id'], $team?->name);
        });

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

        // The "−" button pops whatever is newest on the auction's undo stack. During a
        // sealed round that is very likely a TEAM's sealed bid, so pressing it here
        // would silently retract somebody else's bid instead of lowering a price.
        $closedBids = app(\App\Services\Auction\ClosedBidService::class);
        if ($closedBids->hasOpenRound($player)) {
            return response()->json([
                'success' => false,
                'message' => 'A sealed round is running for this player — use the sealed-bid panel.',
            ], 422);
        }

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

        // Templates this auction may render with: its own, plus the shared globals.
        $displayTemplates = $this->selectableTemplates(\App\Models\AuctionTemplate::TYPE_LIVE_DISPLAY);
        $tickerTemplates = $this->selectableTemplates(\App\Models\AuctionTemplate::TYPE_TICKER);

        return view('backend.pages.auctions.edit', compact(
            'auction',
            'organizations',
            'tournaments',
            'availablePlayers',
            'existingPools',
            'unpooled',
            'budgetTeams',
            'teamBudgets',
            'displayTemplates',
            'tickerTemplates'
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
            // Open-round per-player ceiling, as a share of a team's allocation. Blank means no
            // ceiling; 0 would mean "may not bid at all", so the floor is 1.
            'max_bid_pct_of_budget' => 'nullable|numeric|min:1|max:100',
            'auction_template_id' => 'nullable|exists:auction_templates,id',
            'ticker_template_id' => 'nullable|exists:auction_templates,id',
            'default_retained_value' => 'nullable|numeric|min:0',
            'expected_retained_per_team' => 'nullable|integer|min:0|max:50',
            // Sealed-round rules. A step of 0 is a configuration error, not "any amount
            // is legal", so it is refused here rather than defended against downstream.
            'closed_bid_step' => 'nullable|numeric|min:0.01',
            'closed_bid_max_pct_of_budget' => 'nullable|numeric|min:1|max:100',
            'closed_bid_max_rebid_rounds' => 'nullable|integer|min:0|max:5',
            'closed_bid_timer_seconds' => 'nullable|integer|min:5|max:600',
            'closed_bid_requires_acceptance' => 'nullable|boolean',
            'closed_bid_auto_rebid' => 'nullable|boolean',
            'closed_bid_tie_breaker' => 'nullable|in:lot,manual',
            // What amounts are called on every screen.
            'amount_unit' => 'nullable|in:points,coins,usd,custom',
            'amount_unit_label' => 'nullable|string|max:30|required_if:amount_unit,custom',
            'show_squad_values' => 'nullable|boolean',
            'show_acquisition_badge' => 'nullable|boolean',
            // Whether this auction ignores the tournament's squad rules — Auction::rule().
            'overrides_tournament_rules' => 'nullable|boolean',
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
            // See the matching rule in store().
            'open_bid_mode' => 'nullable|in:online,offline',
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
        $this->assertClosedBidRuleIsSatisfiable($validated);
        $this->assertBidLadderCoversBasePrice($validated);

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
                // Not `?? $auction->x` — preserve-on-absent would make the ceiling impossible
                // to clear once set, the trap the colour fields fell into.
                'max_bid_pct_of_budget' => $validated['max_bid_pct_of_budget'] ?? null,
                'min_price_per_player' => $validated['min_price_per_player'] ?? $auction->min_price_per_player,
                // Deliberately NOT `?? $auction->x` — preserve-on-absent would make these
                // impossible to clear, the same trap the colour fields fell into.
                'auction_template_id' => $validated['auction_template_id'] ?? null,
                'ticker_template_id' => $validated['ticker_template_id'] ?? null,
                /*
                 * Derived from Team Size, never taken from the request.
                 *
                 * The wizard used to ask for a minimum AND a maximum, and nothing in the auction
                 * used the difference — the only thing the pair did was refuse the whole save
                 * when they disagreed, which is how a maximum of 8 under a minimum of 11 silently
                 * rejected an entire form. One number now feeds both columns, so the reserve rule
                 * and the live screens keep reading the fields they always have and the two can
                 * never contradict each other again.
                 */
                'max_squad_size' => $validated['min_squad_size'] ?? Auction::DEFAULT_MIN_SQUAD_SIZE,
                'default_retained_value' => $validated['default_retained_value'] ?? null,
                'expected_retained_per_team' => $validated['expected_retained_per_team'] ?? null,
                'closed_bid_step' => $validated['closed_bid_step'] ?? null,
                'closed_bid_max_pct_of_budget' => $validated['closed_bid_max_pct_of_budget'] ?? null,
                'closed_bid_max_rebid_rounds' => $validated['closed_bid_max_rebid_rounds'] ?? null,
                'closed_bid_timer_seconds' => $validated['closed_bid_timer_seconds'] ?? null,
                'closed_bid_requires_acceptance' => $request->boolean('closed_bid_requires_acceptance', true),
                'closed_bid_auto_rebid' => $request->boolean('closed_bid_auto_rebid'),
                'closed_bid_tie_breaker' => $validated['closed_bid_tie_breaker'] ?? null,
                'amount_unit' => $validated['amount_unit'] ?? $auction->amount_unit ?? Auction::UNIT_POINTS,
                'amount_unit_label' => $validated['amount_unit_label'] ?? null,
                /*
                 * has() then boolean(), not boolean(default: true).
                 *
                 * An unticked checkbox posts nothing, and boolean($key, true) returns the
                 * default when the key is ABSENT — so the toggle could be switched on and never
                 * off. The edit form posts a hidden 0 alongside it so the key is always present
                 * and a tick is honoured either way; the create wizard renders no such field, so
                 * absence there correctly means "new auctions show values".
                 */
                'overrides_tournament_rules' => $request->boolean('overrides_tournament_rules'),
                'show_acquisition_badge' => $request->boolean('show_acquisition_badge', true),
                'show_squad_values' => $request->has('show_squad_values')
                    ? $request->boolean('show_squad_values')
                    : true,
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
            ], $brandingData, $this->biddingModeData($validated)));

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

            $this->syncTeamBudgets($auction, $request->input('team_budgets'));
        });

        return redirect()->route('admin.auctions.index')->with('success', 'Auction configuration updated successfully.');
    }

    /**
     * The auction broadcast screens, in one place.
     *
     * The ticker and the LED wall were reachable only from two ad-hoc links on a single
     * auction's page, so nobody running a stream could find them.
     */
    public function liveTickerIndex()
    {
        $this->authorize('auction.view');

        $auctions = Auction::with('tournament')
            // Live auctions first — that is what somebody opening this page wants.
            ->orderByRaw("CASE WHEN status = 'running' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('backend.pages.auctions.live-ticker-index', [
            'auctions' => $auctions,
            'breadcrumbs' => [
                'title' => __('Auction Broadcast Screens'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                ],
            ],
        ]);
    }

    /**
     * Persist per-team budget overrides.
     *
     * Blank clears the override so the uniform cap applies again — it must NOT write 0,
     * because a zero row legitimately means "this team has no money" and allocatedBudget()
     * honours it.
     *
     * Keys arrive from the request, so only teams that really belong to this auction's
     * tournament may be written; without that check any actual_team_id could be injected,
     * including another organization's. Shared by store() and update() precisely so that
     * check cannot drift between them.
     *
     * @param  mixed  $budgets
     */
    protected function syncTeamBudgets(Auction $auction, $budgets): void
    {
        if (! is_array($budgets)) {
            return;
        }

        $eligibleTeamIds = ActualTeam::forTournament($auction->tournament_id)
            ->pluck('id')
            ->flip();

        foreach ($budgets as $teamId => $budget) {
            if (! $eligibleTeamIds->has((int) $teamId)) {
                continue;
            }

            if ($budget === null || $budget === '') {
                AuctionTeamBudget::where('auction_id', $auction->id)
                    ->where('actual_team_id', (int) $teamId)
                    ->delete();

                continue;
            }

            AuctionTeamBudget::updateOrCreate(
                ['auction_id' => $auction->id, 'actual_team_id' => (int) $teamId],
                ['organization_id' => $auction->organization_id, 'budget' => $budget]
            );
        }
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
    /**
     * Clear finished rows out of the outbox.
     *
     * Only what has already resolved: `sent` and `skipped`. A pending email is still owed
     * to somebody and a failed one is the record of a delivery that has to be chased, so
     * neither can be cleared here — the outbox would otherwise become a way to lose mail
     * quietly. Requeue a failure first if you want it gone.
     */
    public function clearEmailLog(Auction $auction, Request $request)
    {
        $this->authorize('auction.edit');

        $validated = $request->validate([
            'scope' => 'required|in:sent,skipped,resolved',
        ]);

        $statuses = match ($validated['scope']) {
            'sent' => [\App\Models\AuctionPendingEmail::STATUS_SENT],
            'skipped' => [\App\Models\AuctionPendingEmail::STATUS_SKIPPED],
            default => [
                \App\Models\AuctionPendingEmail::STATUS_SENT,
                \App\Models\AuctionPendingEmail::STATUS_SKIPPED,
            ],
        };

        // Scoped to THIS auction as well as the status: the id in the URL is the only thing
        // separating one organizer's outbox from another's.
        $deleted = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)
            ->whereIn('status', $statuses)
            ->delete();

        return back()->with('success', $deleted === 0
            ? 'Nothing to clear.'
            : "Cleared {$deleted} finished email(s) from the log.");
    }

    /**
     * The email as the recipient would receive it.
     *
     * Rendered from the same mailable the queue sends, so what is previewed is what goes
     * out rather than a second rendering that can drift. Read-only: previewing must never
     * send, and must never mark a pending row as handled.
     */
    public function previewEmail(Auction $auction, \App\Models\AuctionPendingEmail $email)
    {
        $this->authorize('auction.edit');

        // Route-model binding hands over whatever id is in the URL, and nothing else on
        // this route ties the email to the auction.
        if ((int) $email->auction_id !== (int) $auction->id) {
            abort(404);
        }

        try {
            $html = app(\App\Services\Auction\AuctionMailService::class)->renderPreview($email);
        } catch (\Throwable $e) {
            // A template that throws is exactly what somebody is previewing to find out
            // about, so show the reason rather than a blank page or a 500.
            $html = '<pre style="padding:24px;font:14px/1.5 monospace;color:#b91c1c;white-space:pre-wrap">'
                . e('This email could not be rendered:' . PHP_EOL . PHP_EOL . $e->getMessage())
                . '</pre>';
        }

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

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
    /**
     * One player's wall card as a PNG.
     *
     * `?result=1` keeps the outcome on it — the SOLD stamp and the price. Without it the card
     * is the player as they looked before the hammer fell, which is the version wanted for
     * pre-auction promotion.
     */
    public function downloadPlayerCard(Request $request, Auction $auction, AuctionPlayer $auctionPlayer, AuctionCardRenderer $cards)
    {
        $this->authorize('auction.view');

        if ((int) $auctionPlayer->auction_id !== (int) $auction->id) {
            abort(404);
        }

        /*
         * Chrome needs seconds per card, and PHP's default ceiling is 30 — so a single card
         * could die halfway and a zip of a pool always did ("Maximum execution time of 30
         * seconds exceeded"). Lifted here rather than in php.ini, so the feature does not
         * depend on how a particular machine is configured; production already allows 600s at
         * both FPM and nginx, and this makes a development box behave the same.
         *
         * Note the gateway still has the last word: nginx's fastcgi_read_timeout will cut a
         * very large zip regardless. That is the reason the per-player download exists.
         */
        set_time_limit(0);

        $withResult = $request->boolean('result');
        $path = $cards->render($auction, $auctionPlayer, $withResult);

        return response()->download($path, $cards->filename($auctionPlayer, $withResult), [
            'Content-Type' => 'image/png',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Every player's card, as one zip.
     *
     * A browser is started per card, so this is seconds per player rather than milliseconds —
     * the reason it is a separate deliberate action rather than something the page does on
     * load. A pool of two hundred will take minutes; that is the honest cost of rendering the
     * real card rather than approximating it.
     */
    public function downloadPlayerCards(Request $request, Auction $auction, AuctionCardRenderer $cards)
    {
        $this->authorize('auction.view');

        /*
         * Chrome needs seconds per card, and PHP's default ceiling is 30 — so a single card
         * could die halfway and a zip of a pool always did ("Maximum execution time of 30
         * seconds exceeded"). Lifted here rather than in php.ini, so the feature does not
         * depend on how a particular machine is configured; production already allows 600s at
         * both FPM and nginx, and this makes a development box behave the same.
         *
         * Note the gateway still has the last word: nginx's fastcgi_read_timeout will cut a
         * very large zip regardless. That is the reason the per-player download exists.
         */
        set_time_limit(0);

        $withResult = $request->boolean('result');

        /*
         * An optional subset, given as PLAYER ids because that is what the pools screen's
         * checkboxes carry — its selection drives the remove action too, and re-keying it to
         * auction-player ids to suit this download would have put that at risk for no gain.
         * Mapped through this auction, so an id from elsewhere selects nothing.
         */
        $only = array_filter(array_map('intval', (array) $request->query('players', [])));

        $players = $auction->auctionPlayers()
            ->with('player')
            ->whereHas('player')
            ->when($only !== [], fn ($q) => $q->whereIn('player_id', $only))
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->orderBy('id')
            ->get();

        if ($players->isEmpty()) {
            return back()->with('error', 'This auction has no players to render.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'auction-cards-') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create the zip file.');
        }

        $rendered = [];
        // Kept so a total failure can SAY why instead of pointing at the log. Every card fails
        // for the same reason in practice — the renderer cannot reach the page — and making the
        // operator go and read a log file to learn that is not an answer.
        $firstFailure = null;

        foreach ($players as $ap) {
            try {
                $png = $cards->render($auction, $ap, $withResult);
                $zip->addFile($png, $cards->filename($ap, $withResult));
                // Kept until close(): ZipArchive reads the files when the archive is written,
                // so deleting one here would put an empty entry in the zip.
                $rendered[] = $png;
            } catch (\Throwable $e) {
                $firstFailure ??= $e->getMessage();
                /*
                 * One unrenderable player must not lose the other 199. A missing photo or a
                 * name Chrome chokes on is exactly the kind of thing found by exporting, so the
                 * failure is logged and the rest of the pool still arrives.
                 */
                Log::warning('Auction card render failed', [
                    'auction_player_id' => $ap->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $zip->close();

        foreach ($rendered as $png) {
            @unlink($png);
        }

        if ($rendered === []) {
            @unlink($zipPath);

            return back()->with('error', 'None of the cards could be rendered. ' . ($firstFailure ?: 'See the log for why.'));
        }

        $name = sprintf('auction-%d-cards%s.zip', $auction->id, $withResult ? '-sold' : '');

        return response()->download($zipPath, $name)->deleteFileAfterSend(true);
    }

    /**
     * Begin rendering a set of cards, and hand back a token to watch it with.
     *
     * Returns immediately. The old synchronous download held the connection open for the whole
     * render — minutes for a pool, longer than nginx allows for a whole auction — with nothing
     * on screen but a browser spinner, so an operator could not tell a slow export from a dead
     * one, and the largest exports never arrived at all.
     */
    public function startCardExport(Request $request, Auction $auction)
    {
        $this->authorize('auction.view');

        $withResult = $request->boolean('result');

        /*
         * An optional subset, given as PLAYER ids because that is what the pools screen's
         * checkboxes carry — its selection drives the remove action too, and re-keying it to
         * auction-player ids to suit this download would have put that at risk for no gain.
         * Mapped through this auction, so an id from elsewhere selects nothing.
         */
        $only = array_filter(array_map('intval', (array) $request->input('players', [])));

        /*
         * Which outcome, if the operator asked for one.
         *
         * Filtered HERE rather than by posting a list of ids: a 200-player export should not be
         * a 200-id request body, and a list built from whatever the page last polled can have
         * drifted from what the auction is by the time the job runs. Composes with $only, so a
         * subset ticked on the Pools screen can still be narrowed to the sold ones.
         */
        $status = in_array($request->input('status'), ['sold', 'unsold'], true)
            ? $request->input('status')
            : 'all';

        /*
         * One pool, when the operator picked one.
         *
         * Scoped through THIS auction so an id from another auction selects nothing rather
         * than exporting somebody else's players. Composes with $status, which is the whole
         * point: "the sold players from Pool 1" is a poster run people actually want, and
         * neither filter alone expresses it.
         */
        $poolId = (int) $request->input('pool_id');
        $pool = $poolId > 0
            ? AuctionPool::where('auction_id', $auction->id)->find($poolId)
            : null;

        if ($poolId > 0 && ! $pool) {
            return response()->json(['message' => 'That pool is not part of this auction.'], 422);
        }

        $ids = $auction->auctionPlayers()
            ->whereHas('player')
            ->when($only !== [], fn ($q) => $q->whereIn('player_id', $only))
            /*
             * `sold` asks the same question the badge asks — is there a buying team —
             * rather than `status = 'sold'`. Two sources of truth for one fact is how a zip
             * ends up holding a poster with no price under a filter that promised sold ones.
             */
            ->when($status === 'sold', fn ($q) => $q->whereNotNull('sold_to_team_id'))
            // Matches AuctionPosterData::status(), skipped included: a player nobody called
            // back is one the auction finished without selling.
            ->when($status === 'unsold', fn ($q) => $q
                ->whereNull('sold_to_team_id')
                ->whereIn('status', ['unsold', 'passed', 'skipped']))
            /*
             * Matched on where a player came FROM as well as where they are now.
             *
             * A sold player still sits in the pool they were bought out of, but an unsold one
             * has been moved to the auction's shared unsold pile — so filtering on
             * auction_pool_id alone would return every sold player from Pool 1 and none of its
             * unsold ones, which is exactly the run this feature exists for.
             */
            ->when($pool, fn ($q) => $q->where(fn ($w) => $w
                ->where('auction_pool_id', $pool->id)
                ->orWhere('source_pool_id', $pool->id)))
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            // Say which nothing this is. "No players" on an auction with two hundred of them
            // reads as a broken export rather than as an empty filter.
            return response()->json([
                'message' => match (true) {
                    $pool && $status === 'sold' => sprintf('No players from %s have been sold yet.', $pool->name),
                    $pool && $status === 'unsold' => sprintf('No players from %s have gone unsold.', $pool->name),
                    (bool) $pool => sprintf('%s has no players to render.', $pool->name),
                    $status === 'sold' => 'No players have been sold yet, so there are no sold posters to render.',
                    $status === 'unsold' => 'No players have gone unsold, so there are no unsold posters to render.',
                    $only !== [] => 'None of the selected players are in this auction.',
                    default => 'This auction has no players to render.',
                },
            ], 422);
        }

        /*
         * Sweep this auction's old exports before adding another.
         *
         * A zip of 200 cards is tens of megabytes, and the box producing them is the same one
         * serving the auction. An hour is long enough that a download interrupted by a flaky
         * connection can still be retried, and short enough that a day of exporting does not
         * fill the disk.
         */
        AuctionCardExport::where('auction_id', $auction->id)
            ->where('created_at', '<', now()->subHour())
            ->get()
            ->each
            ->discard();

        /*
         * Which design to render.
         *
         * Absent means the LED wall's own card, screenshotted from the wall so the hall and the
         * download cannot disagree. Given, it is an auction poster from the drag editor —
         * landscape or portrait, drawn with GD, no browser per player. Resolved through THIS
         * auction's tournament and checked to be an auction poster type, so an id from
         * elsewhere, or a match poster, selects nothing rather than rendering a design that
         * has no idea what a player is.
         */
        $templateId = (int) $request->input('template_id');
        $template = null;

        if ($templateId > 0) {
            $template = TournamentTemplate::where('id', $templateId)
                ->where('tournament_id', $auction->tournament_id)
                ->whereIn('type', [
                    TournamentTemplate::TYPE_AUCTION_POSTER,
                    TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
                ])
                ->first();

            if (! $template) {
                return response()->json([
                    'message' => 'That poster template does not belong to this auction\'s tournament.',
                ], 422);
            }
        }

        $token = (string) Str::uuid();

        $export = AuctionCardExport::create([
            'auction_id' => $auction->id,
            'user_id' => Auth::id(),
            'token' => $token,
            'with_result' => $withResult,
            'tournament_template_id' => $template?->id,
            'auction_player_ids' => $ids,
            'total' => count($ids),
            'status' => AuctionCardExport::STATUS_QUEUED,
            'path' => AuctionCardExport::DIRECTORY . '/' . $token . '.zip',
            /*
             * The zip says what is in it. An operator running a sold export and an unsold one
             * back to back otherwise ends up with two files of the same name in one folder,
             * and the second silently becomes "(1)".
             */
            'filename' => sprintf(
                'auction-%d-%s%s%s.zip',
                $auction->id,
                $pool ? \Illuminate\Support\Str::slug($pool->name) . '-' : '',
                $status === 'all' ? 'all' : $status,
                $template ? '-posters' : '-cards'
            ),
        ]);

        RenderAuctionCards::dispatch($export->id);

        /*
         * Re-read before answering. Under QUEUE_CONNECTION=sync the dispatch above ran the
         * whole render inline, so by now this export may already be finished — and replying
         * with the row as it was created would leave the page polling for something that has
         * already happened.
         */
        return response()->json($export->refresh()->toProgressPayload());
    }

    /** How far the export has got. Polled about once a second, so it stays small. */
    public function cardExportProgress(Auction $auction, string $token)
    {
        $this->authorize('auction.view');

        return response()->json($this->findExport($auction, $token)->toProgressPayload());
    }

    /**
     * The finished zip.
     *
     * Kept on disk after sending rather than deleted: a download interrupted halfway is a
     * normal thing to want to retry, and re-rendering two hundred cards to recover from a
     * dropped connection is not a reasonable price. The hourly sweep in startCardExport()
     * clears them.
     */
    public function cardExportDownload(Auction $auction, string $token)
    {
        $this->authorize('auction.view');

        $export = $this->findExport($auction, $token);

        abort_unless($export->status === AuctionCardExport::STATUS_DONE, 409, 'This export has not finished.');

        $path = $export->absolutePath();

        abort_unless($path && is_file($path), 410, 'This export has expired. Please run it again.');

        return response()->download($path, $export->filename);
    }

    /**
     * Stop a running export.
     *
     * Marked rather than deleted, so the job that is mid-render sees the change and stops —
     * deleting the row would leave the job writing into a zip nothing points at. Whatever it
     * managed to render is discarded with it: half a pool of posters is not a deliverable, and
     * keeping it would mean an operator downloading an archive that silently misses players.
     */
    public function cancelCardExport(Auction $auction, string $token)
    {
        $this->authorize('auction.view');

        $export = $this->findExport($auction, $token);

        if ($export->isFinished()) {
            return response()->json(['message' => 'That export has already finished.'], 422);
        }

        $export->update([
            'status' => AuctionCardExport::STATUS_CANCELLED,
            'message' => sprintf('Stopped after %d of %d.', $export->settled(), $export->total),
        ]);

        if ($export->path && Storage::disk(AuctionCardExport::DISK)->exists($export->path)) {
            Storage::disk(AuctionCardExport::DISK)->delete($export->path);
            $export->update(['path' => null]);
        }

        return response()->json($export->fresh()->toProgressPayload());
    }

    /**
     * One player's poster, rendered now and downloaded.
     *
     * Not through the export queue: that exists because three hundred cards outlive a request,
     * and a single poster drawn with GD is milliseconds. Sending one player through a job, a zip
     * and a progress dialog to fetch one PNG is machinery for its own sake.
     */
    public function playerPoster(Request $request, Auction $auction, AuctionPlayer $auctionPlayer)
    {
        $this->authorize('auction.view');

        abort_unless($auctionPlayer->auction_id === $auction->id, 404);

        $template = TournamentTemplate::where('tournament_id', $auction->tournament_id)
            ->whereIn('type', [
                TournamentTemplate::TYPE_AUCTION_POSTER,
                TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
            ])
            ->when($request->integer('template'), fn ($q, $id) => $q->whereKey($id))
            ->orderByDesc('is_default')
            ->first();

        $auctionPlayer->loadMissing(['player', 'soldToTeam', 'pool', 'sourcePool']);

        $name = app(\App\Services\Auction\AuctionCardRenderer::class)->filename($auctionPlayer, true);

        /*
         * No designed poster: fall back to the LED wall card, which every auction has.
         *
         * This used to redirect back with "No auction poster has been designed for this
         * tournament yet." — and the pools screen hid the download control in exactly the same
         * case, so the message was unreachable and the screen simply had no download on it. An
         * organizer reading that screen after a pool completes concludes the download broke when
         * the players sold. The bulk export already falls back this way; the single one did not.
         */
        if (! $template) {
            $path = app(\App\Services\Auction\AuctionCardRenderer::class)
                ->render($auction, $auctionPlayer, true);

            return response()->download($path, $name)->deleteFileAfterSend(true);
        }

        $stored = app(\App\Services\Poster\TemplateRenderService::class)->renderTemplate(
            $template,
            app(\App\Services\Poster\AuctionPosterData::class)->forPlayer($auctionPlayer),
            false,
            // Hide anything with no value, so one design serves the lot announcement and the
            // sold poster alike.
            true
        );

        $path = Storage::disk('public')->path($stored);

        // Deleted after sending: it is a one-off render, not something the public disk should keep.
        return response()->download($path, $name)->deleteFileAfterSend(true);
    }

    /** Delete one archive and its zip. */
    public function deleteCardExport(Auction $auction, string $token)
    {
        $this->authorize('auction.edit');

        $export = $this->findExport($auction, $token);

        // A running export is stopped first, or its job would go on writing to a deleted row.
        if (! $export->isFinished()) {
            $export->update(['status' => AuctionCardExport::STATUS_CANCELLED]);
        }

        $export->discard();

        return back()->with('success', 'Archive deleted.');
    }

    /**
     * Every archive this auction has produced.
     *
     * The exports were invisible once the dialog was closed: the zip stayed on disk for an hour
     * and there was no way to fetch it again, see what had already been generated, or delete one
     * — so a second pool of 300 posters was the only way to recover a download that had been
     * dismissed.
     */
    public function cardExports(Auction $auction)
    {
        $this->authorize('auction.view');

        $exports = AuctionCardExport::where('auction_id', $auction->id)
            ->with('tournamentTemplate:id,name,type')
            ->latest('id')
            ->paginate(30);

        return view('backend.pages.auctions.card-exports', [
            'auction' => $auction,
            'exports' => $exports,
            'breadcrumbs' => [
                'title' => __('Poster Archives'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    /**
     * Scoped to the auction in the URL as well as matched on the token.
     *
     * The token alone would be enough to find the row, but binding it to the auction means the
     * `auction.view` check above is a check on the auction the export actually belongs to,
     * rather than on whichever auction the caller chose to put in the path.
     */
    private function findExport(Auction $auction, string $token): AuctionCardExport
    {
        return AuctionCardExport::where('auction_id', $auction->id)
            ->where('token', $token)
            ->firstOrFail();
    }

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
