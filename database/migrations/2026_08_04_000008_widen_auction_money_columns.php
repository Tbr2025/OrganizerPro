<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Bring every auction money column onto decimal(15,2), matching
     * `auctions.max_budget_per_team` (widened in 2026_07_15_121540).
     *
     * Two real problems before this:
     *  - auction_players.base_price / current_price / retained_price were
     *    `int unsigned`, so any fractional price was silently truncated.
     *  - final_price / starting_price / auctions.base_price were decimal(10,2),
     *    capping at 99,999,999.99 — below the 100,000,000 default per-team budget,
     *    so a single large sale could overflow the column it is written to.
     *
     * Widening is lossless in both cases.
     */
    public function up(): void
    {
        $this->modify('auction_players', [
            'base_price' => 'DECIMAL(15,2) NOT NULL DEFAULT 1000000',
            'current_price' => 'DECIMAL(15,2) NULL',
            'starting_price' => 'DECIMAL(15,2) NULL',
            'final_price' => 'DECIMAL(15,2) NULL',
            'retained_price' => 'DECIMAL(15,2) NULL',
        ]);

        $this->modify('auctions', [
            'base_price' => 'DECIMAL(15,2) NOT NULL DEFAULT 1',
        ]);

        $this->modify('auction_team_budgets', [
            'budget' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        ]);

        $this->modify('auction_bids', [
            'amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        ]);
    }

    public function down(): void
    {
        // Narrowing could truncate real data, so this is deliberately not reversed.
    }

    /**
     * @param  array<string, string>  $columns
     */
    private function modify(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
        }
    }
};
