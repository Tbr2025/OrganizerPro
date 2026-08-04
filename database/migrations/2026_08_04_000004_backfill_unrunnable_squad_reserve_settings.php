<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Repair auctions the squad-reserve defaults would have deadlocked.
     *
     * The reserve rule holds back `(slots_remaining - 1) * min_price_per_player`, so
     * the rule is only satisfiable when a full squad is affordable at all:
     *
     *     min_squad_size * min_price_per_player <= max_budget_per_team
     *
     * Existing auctions inherited the column defaults (11 slots x 1,000,000), which
     * for anything with a purse under 11,000,000 makes the maximum allowable first
     * bid zero — i.e. no player can ever be bought. Those auctions are rebased onto
     * their own base_price, which is the real floor price of a player in that
     * auction, and clamped so a full squad always fits.
     */
    public function up(): void
    {
        $auctions = DB::table('auctions')
            ->select('id', 'max_budget_per_team', 'base_price', 'min_squad_size', 'min_price_per_player')
            ->get();

        foreach ($auctions as $auction) {
            $budget = (float) ($auction->max_budget_per_team ?? 0);
            $squad = max(1, (int) ($auction->min_squad_size ?? 11));
            $minPrice = (float) ($auction->min_price_per_player ?? 0);

            // Already satisfiable — leave it alone.
            if ($budget <= 0 || $squad * $minPrice <= $budget) {
                continue;
            }

            // Prefer the auction's own base price; fall back to an even split of the
            // purse across the squad if that is still too high.
            $candidate = (float) ($auction->base_price ?? 0);
            if ($candidate <= 0 || $squad * $candidate > $budget) {
                $candidate = floor($budget / $squad);
            }

            DB::table('auctions')
                ->where('id', $auction->id)
                ->update(['min_price_per_player' => max(0, $candidate)]);
        }
    }

    public function down(): void
    {
        // Data repair: nothing to reverse.
    }
};
