<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One team's participation in one sealed round.
 *
 * Deliberately NOT columns on `auction_bids`:
 *
 *  - Participation exists without an amount. A team that accepted but has not yet
 *    submitted, a team that declined, a team that withdrew before typing anything, a
 *    team *required* to re-bid — none of those can be given a bid row without inventing
 *    a fake amount.
 *  - `auction_bids` is append-only so the undo walker can read it, whereas a round needs
 *    one *replaceable* standing amount per team. The unique index below gives that for
 *    free; bolting round/withdrawn columns onto an append log would force every read to
 *    be "latest non-void row per (team, round)" — which is exactly the latest-vs-highest
 *    defect that already exists in `fetchSealedBids()`.
 *  - Keeping losing sealed amounts out of `auction_bids` means every existing reader of
 *    that table — the public active-player feed, the bidding page, the report, the
 *    ticker — is safe by default rather than needing a filter somebody might forget.
 *
 * The bridge back is a single audit `auction_bids` row written at award time, so the
 * existing undo-a-sale path keeps working unchanged.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('auction_closed_bid_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_closed_bid_round_id')
                ->constrained('auction_closed_bid_rounds')
                ->cascadeOnDelete();
            // Denormalised so an entry can be org-scoped without joining the round.
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('actual_team_id')->constrained('actual_teams')->cascadeOnDelete();

            // invited, accepted, declined, submitted, withdrawn, must_rebid, may_opt_in, no_entry
            $table->string('state', 24)->default('invited');

            // NULL until submitted. "No bid" and "a bid of 0" must stay distinguishable.
            $table->decimal('amount', 15, 2)->nullable();

            // What the team was actually SHOWN when it accepted. This is what defends a
            // dispute of the form "the system told me I could bid 7M".
            $table->decimal('ceiling_at_entry', 15, 2)->nullable();
            $table->decimal('per_player_cap_at_entry', 15, 2)->nullable();
            $table->decimal('reserve_at_entry', 15, 2)->nullable();
            $table->unsignedInteger('slots_remaining_at_entry')->nullable();

            // Tied teams must re-bid; teams below them may opt in.
            $table->boolean('required')->default(false);

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();

            // Timestamps and an actor rather than a boolean: defending a disputed round
            // needs who and when, and un-withdrawing becomes a second fact rather than
            // the loss of the first one.
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('withdrawn_by')->nullable();
            $table->string('withdrawn_by_role', 8)->nullable(); // team | admin
            $table->timestamp('reinstated_at')->nullable();
            $table->unsignedBigInteger('reinstated_by')->nullable();

            // Admin +/-/custom edits. Duplicates the action log on purpose: the log is
            // deleted by rebidPlayer(), this column is not.
            $table->json('adjustments')->nullable();
            $table->unsignedInteger('adjusted_count')->default(0);

            $table->timestamps();

            // One standing amount per team per round, enforced by the database, so a
            // double submit cannot stack two amounts for one team.
            $table->unique(['auction_closed_bid_round_id', 'actual_team_id'], 'acbe_round_team_unique');
            // Serves the winner query.
            $table->index(['auction_closed_bid_round_id', 'state', 'amount'], 'acbe_round_state_amount_idx');

            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('withdrawn_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reinstated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('auction_players', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_players', 'closed_bid_round_id')) {
                // The CURRENT round. The sealed phase is derived from the pointed-to
                // round's state, so there is no second copy of the state to drift.
                $table->unsignedBigInteger('closed_bid_round_id')->nullable()->index();
                $table->foreign('closed_bid_round_id')
                    ->references('id')->on('auction_closed_bid_rounds')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('auction_players', 'closed_bid_round_id')) {
            Schema::table('auction_players', function (Blueprint $table) {
                $table->dropForeign(['closed_bid_round_id']);
                $table->dropColumn('closed_bid_round_id');
            });
        }

        Schema::dropIfExists('auction_closed_bid_entries');
    }
};
