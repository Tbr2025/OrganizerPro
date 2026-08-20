<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\AuctionPlayer;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The tournament's player history: how every player was acquired, from which pool, for how much,
 * and when.
 *
 * One class rather than a controller method because the screen and the PDF must not be able to
 * disagree — an export that quietly answers a different question from the page above it is worse
 * than no export. Both call build() with the same filters.
 *
 * The acquisition rules themselves are NOT restated here: SquadAcquisitionService already owns
 * "what is a buy and what is a keep", and this asks it, so a player is described the same way in
 * a report as in their own squad list.
 */
class PlayerHistoryQuery
{
    /** How many rows a single PDF will carry before it starts saying what it left out. */
    public const PDF_ROW_CAP = 2000;

    /**
     * The two zones this report is read in.
     *
     * Auctions here are run from the Gulf for competitions whose players are largely in India, so
     * a single time column starts an argument about which one it is. Both are printed, always,
     * and this is the one place that decides them.
     *
     * @return array<string, string>
     */
    public static function zones(): array
    {
        return [
            'Asia/Kolkata' => 'IST',
            'Asia/Dubai' => 'Dubai',
        ];
    }

    /** The query-string keys this report owns — for filter_url() and the "filters applied" strip. */
    public static function filterKeys(): array
    {
        return ['auction_id', 'pool_id', 'team', 'acquisition', 'status', 'search',
            'price_min', 'price_max', 'date_from', 'date_to', 'tz', 'sort'];
    }

    /**
     * Read the filters off the request, normalised so the views and the PDF never have to guess
     * whether a value is an empty string, a null or a zero.
     *
     * @return array<string, mixed>
     */
    public function filters(Request $request): array
    {
        $tz = (string) $request->query('tz', 'Asia/Kolkata');

        return [
            'auction_id' => $request->query('auction_id') ?: null,
            'pool_id' => $request->query('pool_id') ?: null,
            'team' => $request->query('team') ?: null,
            'acquisition' => $request->query('acquisition') ?: null,
            'status' => $request->query('status') ?: null,
            'search' => trim((string) $request->query('search', '')),
            'price_min' => is_numeric($request->query('price_min')) ? (float) $request->query('price_min') : null,
            'price_max' => is_numeric($request->query('price_max')) ? (float) $request->query('price_max') : null,
            'date_from' => $request->query('date_from') ?: null,
            'date_to' => $request->query('date_to') ?: null,
            // An unknown zone would silently shift every date boundary, so it falls back rather
            // than reaching Carbon.
            'tz' => array_key_exists($tz, self::zones()) ? $tz : 'Asia/Kolkata',
            'sort' => $request->query('sort') ?: 'recent',
        ];
    }

    /**
     * The moment this player's status became what it is.
     *
     * Sold players have a real sale time; icon players and anyone still waiting or unsold have
     * only their last status change. One SQL expression rather than a per-row decision, so the
     * date filter and the sort can use it without a subquery each.
     */
    public function eventAtSql(): string
    {
        return 'COALESCE(auction_players.sold_at, auction_players.updated_at)';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuctionPlayer>
     */
    public function build(Tournament $tournament, array $filters): Builder
    {
        $query = AuctionPlayer::query()
            ->whereHas('auction', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with([
                'player:id,name,email,image_path',
                'soldToTeam:id,name,team_logo',
                'team:id,name,team_logo',
                'auction:id,name,tournament_id,amount_unit,amount_unit_label',
                'pool:id,name',
                'sourcePool:id,name',
            ]);

        if ($filters['auction_id']) {
            $query->where('auction_id', $filters['auction_id']);
        }

        /*
         * A pool matches on either column. Unsold players share one pile per auction (the
         * 2026-08-13 merge), so `auction_pool_id` says where they are now and `source_pool_id`
         * is the only record of the pool they were actually bid in — and "who went unsold out of
         * Pool A" is one of the questions this page exists to answer.
         */
        if ($filters['pool_id']) {
            $query->where(fn ($q) => $q
                ->where('auction_pool_id', $filters['pool_id'])
                ->orWhere('source_pool_id', $filters['pool_id']));
        }

        // Whoever holds the player: the buyer for a sale, the keeping team for an icon player.
        if ($filters['team']) {
            $query->where(fn ($w) => $w
                ->where('sold_to_team_id', $filters['team'])
                ->orWhere(fn ($k) => $k->where('is_retained', true)->where('team_id', $filters['team'])));
        }

        // Name OR email. Searching a squad by the address you mailed them at is how an organizer
        // finds one player among six hundred.
        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $query->whereHas('player', fn ($p) => $p->where(
                fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)
            ));
        }

        match ($filters['acquisition']) {
            SquadAcquisitionService::AUCTION => $query->where('status', 'sold'),
            'icon' => $query->where('is_retained', true),
            'none' => $query->where('status', '!=', 'sold')->where('is_retained', false),
            default => null,
        };

        /*
         * `status` is the auction ROW's state, not the player record's. The buckets match
         * AuctionAdminController::auctionedPlayers() exactly — two screens disagreeing about what
         * "unsold" covers is worse than either of them being wrong on its own.
         */
        match ($filters['status']) {
            'sold' => $query->where('status', 'sold'),
            'unsold' => $query->whereIn('status', ['unsold', 'passed', 'skipped']),
            'waiting' => $query->where('status', 'waiting')->where('is_retained', false),
            'on_auction' => $query->where('status', 'on_auction'),
            default => null,
        };

        if ($filters['price_min'] !== null) {
            $query->whereRaw($this->priceSql() . ' >= ?', [$filters['price_min']]);
        }

        if ($filters['price_max'] !== null) {
            $query->whereRaw($this->priceSql() . ' <= ?', [$filters['price_max']]);
        }

        $this->applyDateRange($query, $filters);

        match ($filters['sort']) {
            'price_desc' => $query->orderByRaw($this->priceSql() . ' DESC'),
            'price_asc' => $query->orderByRaw($this->priceSql() . ' ASC'),
            'name' => $query->orderBy(
                \App\Models\Player::select('name')->whereColumn('players.id', 'auction_players.player_id')
            ),
            'lot' => $query->orderByRaw('auction_players.lot_number IS NULL, auction_players.lot_number'),
            default => $query->orderByRaw($this->eventAtSql() . ' DESC'),
        };

        return $query;
    }

    /**
     * Price sorts and filters read one figure whichever way the player arrived.
     *
     * COALESCE order matters: a sold player's price is `final_price`, a kept player's is
     * `retained_price`, and 0 stands for "no price" so those rows sort last rather than first.
     */
    private function priceSql(): string
    {
        return 'COALESCE(auction_players.final_price, auction_players.retained_price, 0)';
    }

    /**
     * Narrow to a date range, read in the zone the user picked.
     *
     * A bare date means nothing until you say whose midnight it is: an evening auction in Dubai
     * runs past midnight in India, so the same sale falls on different days in the two columns
     * this report prints. The filter bar's zone select decides, and the boundaries are converted
     * into app time — what the column is stored in — before they reach SQL.
     *
     * @param  Builder<AuctionPlayer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, array $filters): void
    {
        $tz = $filters['tz'];
        $appTz = config('app.timezone');

        if ($filters['date_from']) {
            $from = Carbon::parse($filters['date_from'], $tz)->startOfDay()->setTimezone($appTz);
            $query->whereRaw($this->eventAtSql() . ' >= ?', [$from->format('Y-m-d H:i:s')]);
        }

        if ($filters['date_to']) {
            $to = Carbon::parse($filters['date_to'], $tz)->endOfDay()->setTimezone($appTz);
            $query->whereRaw($this->eventAtSql() . ' <= ?', [$to->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * Stamp each row with what the table renders, so the Blade holds no acquisition logic.
     *
     * @param  Collection<int, AuctionPlayer>|iterable<AuctionPlayer>  $rows
     */
    public function decorate(iterable $rows): void
    {
        foreach ($rows as $row) {
            $bought = $row->status === 'sold' && $row->sold_to_team_id !== null;

            /*
             * Three states, not two. A player still waiting has been acquired by nobody, and
             * calling that `retained` — which a two-way test does — is the same mislabelling
             * that reading `players.player_mode` produced: every unacquired player described as
             * kept. Null means "no team has them yet", and the views render a status instead.
             */
            $row->acquisition = match (true) {
                $bought => SquadAcquisitionService::AUCTION,
                (bool) $row->is_retained => SquadAcquisitionService::RETAINED,
                default => null,
            };
            $row->holding_team = $row->is_retained ? $row->team : $row->soldToTeam;
            $row->acquisition_price = (float) ($bought ? $row->final_price : $row->retained_price);

            /*
             * Never gated on showsSquadValues() / showsAcquisitionBadge(). Those switches exist
             * to keep prices and badges off PUBLIC and team-facing screens — a rival leaning
             * over a shared table. This report is behind tournament.view and its whole purpose
             * is the numbers, exactly as the existing auction report prints them unconditionally.
             */
            $row->acquisition_label = SquadAcquisitionService::label($row->acquisition);

            $row->price_label = $row->acquisition_price > 0
                ? ($row->auction ? $row->auction->formatAmount($row->acquisition_price) : format_points($row->acquisition_price))
                : '—';

            // The pool they were bid in, which for an unsold player is the only one recorded.
            $row->origin_pool = $row->pool ?: $row->sourcePool;

            $at = $row->sold_at ?: $row->updated_at;
            $row->event_at = $at;
            // Keyed by zone, so a view renders one cell per zones() entry and adding a third
            // zone is a one-line change here rather than a column in four templates.
            $row->event_times = $this->times($at);
        }
    }

    /**
     * One timestamp in both zones, formatted for display.
     *
     * @return array<string, string>
     */
    public function times(?Carbon $at): array
    {
        if (! $at) {
            return array_map(fn () => '—', self::zones());
        }

        $out = [];
        foreach (array_keys(self::zones()) as $zone) {
            $out[$zone] = $at->copy()->setTimezone($zone)->format('d M Y, h:i A');
        }

        return $out;
    }

    /**
     * What the filtered set adds up to.
     *
     * Aggregated in SQL over the whole match, not walked over the current page — a total that
     * silently describes 25 of 300 rows is a wrong number, not a partial one.
     *
     * @param  Builder<AuctionPlayer>  $query
     * @return array<string, mixed>
     */
    public function summary(Builder $query): array
    {
        $totals = (clone $query)
            // No eager loads on an aggregate row: there is no model here to hang relations off,
            // and Laravel would fire a query per `with()` entry against a null key.
            ->setEagerLoads([])
            ->selectRaw('COUNT(*) as players')
            ->selectRaw("SUM(CASE WHEN auction_players.status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->selectRaw('SUM(CASE WHEN auction_players.is_retained = 1 THEN 1 ELSE 0 END) as icons')
            ->selectRaw("SUM(CASE WHEN auction_players.status IN ('unsold', 'passed', 'skipped') THEN 1 ELSE 0 END) as unsold")
            ->selectRaw("SUM(CASE WHEN auction_players.status = 'sold' THEN auction_players.final_price ELSE 0 END) as spend")
            ->selectRaw("MAX(CASE WHEN auction_players.status = 'sold' THEN auction_players.final_price ELSE NULL END) as highest")
            ->reorder()
            ->first();

        $sold = (int) ($totals->sold ?? 0);
        $spend = (float) ($totals->spend ?? 0);

        return [
            'players' => (int) ($totals->players ?? 0),
            'sold' => $sold,
            'icons' => (int) ($totals->icons ?? 0),
            'unsold' => (int) ($totals->unsold ?? 0),
            'spend' => $spend,
            'highest' => (float) ($totals->highest ?? 0),
            'average' => $sold > 0 ? $spend / $sold : 0.0,
        ];
    }

    /** True when any filter is actually narrowing the list. */
    public function isFiltered(array $filters): bool
    {
        foreach (self::filterKeys() as $key) {
            if ($key === 'tz' || $key === 'sort') {
                continue;
            }
            if (($filters[$key] ?? null) !== null && $filters[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The filters in words, for the PDF header — a printed report has no filter bar to look at,
     * so it has to say what it is a report OF.
     *
     * @return list<string>
     */
    public function describe(array $filters, iterable $auctions, iterable $pools, iterable $teams): array
    {
        $name = function (iterable $haystack, $id): ?string {
            foreach ($haystack as $item) {
                if ((int) $item->id === (int) $id) {
                    return $item->name;
                }
            }

            return null;
        };

        $parts = [];

        if ($filters['auction_id']) {
            $parts[] = 'Auction: ' . ($name($auctions, $filters['auction_id']) ?? '#' . $filters['auction_id']);
        }
        if ($filters['pool_id']) {
            $parts[] = 'Pool: ' . ($name($pools, $filters['pool_id']) ?? '#' . $filters['pool_id']);
        }
        if ($filters['team']) {
            $parts[] = 'Team: ' . ($name($teams, $filters['team']) ?? '#' . $filters['team']);
        }
        if ($filters['acquisition']) {
            $parts[] = 'Acquired: ' . match ($filters['acquisition']) {
                SquadAcquisitionService::AUCTION => SquadAcquisitionService::AUCTION_LABEL,
                'icon' => SquadAcquisitionService::RETAINED_LABEL,
                default => 'Not acquired',
            };
        }
        if ($filters['status']) {
            $parts[] = 'Status: ' . ucfirst(str_replace('_', ' ', (string) $filters['status']));
        }
        if ($filters['search'] !== '') {
            $parts[] = 'Search: "' . $filters['search'] . '"';
        }
        if ($filters['price_min'] !== null || $filters['price_max'] !== null) {
            $parts[] = 'Price: ' . format_points($filters['price_min'] ?? 0) . ' – '
                . ($filters['price_max'] !== null ? format_points($filters['price_max']) : 'any');
        }
        if ($filters['date_from'] || $filters['date_to']) {
            $parts[] = 'Dates: ' . ($filters['date_from'] ?: 'start') . ' – ' . ($filters['date_to'] ?: 'today')
                . ' (' . self::zones()[$filters['tz']] . ')';
        }

        return $parts;
    }

    /**
     * Pools belonging to this tournament's auctions, for the filter dropdown.
     *
     * The unsold pile is excluded: it is one shared bucket per auction rather than a pool anyone
     * was assigned to, and picking it would return every unsold player from every pool — which
     * the Status filter already does, more honestly.
     *
     * @return Collection<int, \App\Models\AuctionPool>
     */
    public function pools(Collection $auctionIds): Collection
    {
        return \App\Models\AuctionPool::query()
            ->whereIn('auction_id', $auctionIds)
            ->where(fn ($q) => $q->where('is_unsold_pool', false)->orWhereNull('is_unsold_pool'))
            ->orderBy('sequence')
            ->orderBy('name')
            ->get(['id', 'name', 'auction_id', 'sequence']);
    }
}
