<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the queries every live auction screen issues.
 *
 * `auction_players` had no composite index of any kind — only single columns on `auction_id`,
 * `player_id`, `auction_pool_id`, `lot_number` and `sold_at`. MySQL cannot combine those, so
 * `where auction_id = ? and status = ?` — the most-issued predicate in the whole module, run by
 * the team tick, the organizer poll, the public feed, `sealedTarget()` and `placeBid()` — was a
 * range scan over every row of the auction followed by a filter. And `status`, `is_retained`,
 * `sold_to_team_id` and `team_id` were unindexed entirely.
 *
 * At ~500 rows per auction one screen never noticed. Forty screens on a two-core box did.
 *
 * `status` is left an ENUM. Widening it to varchar is tempting — a previous migration
 * (2026_08_07_000005) already argued against further ENUM MODIFYs and chose string(24) for round
 * state — but MODIFY COLUMN rewrites the whole table, and indexing the ENUM as it stands gets the
 * entire win for none of that risk.
 */
return new class () extends Migration {
    /**
     * Each entry is [table, columns, name]. Ordered by how often the query runs, because that is
     * also the order to keep if any of them ever has to be dropped.
     */
    private const INDEXES = [
        /*
         * The one that matters most: "who is on the block", "what has sold", and every status
         * count. Leading with auction_id also means OrganizationScope's added
         * `organization_id = ?` filters a handful of rows rather than the table.
         */
        ['auction_players', ['auction_id', 'status'], 'auction_players_auction_status_idx'],

        // A team's squad, and the grouped SUM(final_price) behind every purse figure. `final_price`
        // rides along so the sum is answered from the index without touching the row.
        ['auction_players', ['auction_id', 'status', 'sold_to_team_id', 'final_price'], 'auction_players_auction_sold_team_idx'],

        // The retained half of the same purse arithmetic. `team_id` had neither an index nor a
        // foreign key before this.
        ['auction_players', ['auction_id', 'is_retained', 'team_id', 'retained_price'], 'auction_players_auction_retained_team_idx'],

        // The waiting queue and nextPlayer(), which read pool then lot order.
        ['auction_players', ['auction_id', 'auction_pool_id', 'status', 'lot_number'], 'auction_players_auction_pool_status_idx'],

        // Kills the filesort behind `ORDER BY updated_at DESC` on the sold board and the ticker.
        ['auction_players', ['auction_id', 'status', 'updated_at'], 'auction_players_auction_status_updated_idx'],

        // OrganizationScope appends this to every query on the table for any non-Superadmin.
        ['auction_players', ['organization_id'], 'auction_players_organization_idx'],

        // Evaluated on every panel paint by approvedForTournament().
        ['tournament_registrations', ['tournament_id', 'type', 'status'], 'tournament_regs_tournament_type_status_idx'],

        // Every team list ends `ORDER BY name`, and `name` was unindexed.
        ['actual_teams', ['tournament_id', 'name'], 'actual_teams_tournament_name_idx'],

        // `auctions` had no secondary index at all — not even on tournament_id, which is how
        // almost every lookup finds an auction.
        ['auctions', ['tournament_id'], 'auctions_tournament_idx'],
        ['auctions', ['organization_id', 'status'], 'auctions_organization_status_idx'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
                continue;
            }

            // Every column must exist: this runs against databases at different ages, and a
            // missing column should skip the index rather than fail the deploy.
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue 2;
                }
            }

            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                $t->index($columns, $name);
            });
        }
    }

    /**
     * Best-effort, and deliberately so.
     *
     * Several of these lead with a foreign-key column — `tournament_registrations.tournament_id`
     * is the one that bit — and once the composite is the only index MySQL can use to enforce
     * that constraint, it refuses the drop with errno 1553. There is nothing to fix in the
     * constraint; the index simply cannot go until something else covers the column.
     *
     * Skipping it is the right answer rather than dropping the foreign key to satisfy a rollback:
     * these are pure read optimisations, nothing depends on their absence, and a leftover index
     * costs a little write throughput and no correctness. The alternative — recreating a
     * single-column index just to free the composite — leaves the schema in a third state that
     * matches neither before nor after.
     */
    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $t) use ($name) {
                    $t->dropIndex($name);
                });
            } catch (\Throwable $e) {
                // 1553: needed in a foreign key constraint. Anything else is worth surfacing.
                if (! str_contains($e->getMessage(), '1553')) {
                    throw $e;
                }

                report(new \RuntimeException(
                    "Left index {$name} on {$table} in place: a foreign key still needs it.",
                    0,
                    $e
                ));
            }
        }
    }

    /** Idempotent: this migration may meet a database where some of these were added by hand. */
    private function indexExists(string $table, string $name): bool
    {
        return count(DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $name]
        )) > 0;
    }
};
