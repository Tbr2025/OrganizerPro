<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse each auction's per-pool unsold pools into one.
 *
 * Unsold players used to be held in a companion pool per source pool — "Pool A — Unsold",
 * "Pool B — Unsold" — on the theory that final allotment could then be run pool by pool. In
 * practice allotment asks which teams are short of a legal squad and which players are left,
 * and both are properties of the whole auction: divided by origin, the screen showed several
 * short lists that had to be recombined in the operator's head before any of them could be
 * acted on, while a team's remaining slots had to be tracked across all of them.
 *
 * This moves every unsold player into the oldest such pool per auction, renames it, detaches
 * it from its parent, and deletes the ones left empty. Each player's origin has already been
 * copied onto `auction_players.source_pool_id` by the migration before this one, so re-auction
 * can still send them back where they belong.
 *
 * Written to be safely re-runnable: it only ever reads the current state.
 */
return new class extends Migration
{
    public function up(): void
    {
        $auctionIds = DB::table('auction_pools')
            ->where('is_unsold_pool', true)
            ->distinct()
            ->pluck('auction_id');

        foreach ($auctionIds as $auctionId) {
            $pools = DB::table('auction_pools')
                ->where('auction_id', $auctionId)
                ->where('is_unsold_pool', true)
                ->orderBy('id')
                ->get();

            if ($pools->isEmpty()) {
                continue;
            }

            // The oldest becomes the auction's one unsold pool — the same one
            // AuctionPoolService::unsoldPoolFor() now resolves to.
            $keep = $pools->first();

            DB::table('auction_pools')->where('id', $keep->id)->update([
                'name' => 'Unsold',
                'parent_pool_id' => null,
                'updated_at' => now(),
            ]);

            $mergeIds = $pools->skip(1)->pluck('id')->all();

            if ($mergeIds === []) {
                continue;
            }

            /*
             * Players move first, pools are deleted second. The other order would orphan a
             * player onto a pool id that no longer exists, and an auction mid-flight would
             * lose them from every screen that lists a pool's players.
             */
            DB::table('auction_players')
                ->whereIn('auction_pool_id', $mergeIds)
                ->update(['auction_pool_id' => $keep->id, 'updated_at' => now()]);

            DB::table('auction_pools')->whereIn('id', $mergeIds)->delete();
        }
    }

    /**
     * Deliberately irreversible.
     *
     * Splitting the pile back up would mean re-creating pools that have been deleted. The
     * players' origins survive on source_pool_id, so nothing is lost that matters; rebuilding
     * the containers is not something a rollback should invent.
     */
    public function down(): void
    {
        // No-op.
    }
};
