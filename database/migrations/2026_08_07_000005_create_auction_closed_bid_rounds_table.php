<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sealed-bid round for one player.
 *
 * The sealed phase used to have no state of its own: a "closed" bid was an ordinary
 * `auction_bids` row that publicly raised `current_price`, the organizer picked the
 * winner by hand, and a tie resolved to whatever MySQL returned from
 * `ORDER BY amount DESC`. Everything a sealed round needs to be defensible after the
 * event — who was invited, who accepted, when it locked, what the tie was, how the lot
 * was drawn — had nowhere to live.
 *
 * The state lives here rather than on `auction_players.status` deliberately. The player
 * stays `on_auction` for the whole episode, which avoids a third raw-MySQL
 * `ALTER TABLE … MODIFY COLUMN` on that enum, and avoids minting another status that
 * `pollState()` and the allotment queries silently skip — which is exactly how the
 * existing `closed` status became unreachable.
 *
 * `state` and `resolution` are plain strings for the same reason: the two enum
 * extensions on `auction_players.status` had to be written as raw MySQL DDL because
 * enums cannot be extended portably.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('auction_closed_bid_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('auction_player_id')->constrained('auction_players')->cascadeOnDelete();

            // A re-auctioned player gets a whole new sealed episode, numbered from 1 again.
            $table->unsignedTinyInteger('attempt_no')->default(1);
            // 1 .. 1 + closed_bid_max_rebid_rounds.
            $table->unsignedTinyInteger('round_number')->default(1);
            $table->unsignedBigInteger('parent_round_id')->nullable();

            $table->string('state', 24)->default('pending');

            // Config snapshot. Written on every round, not just the first, so each round
            // is independently defensible: "the rule in force for round 2 was step
            // 100000, cap 70%".
            $table->decimal('floor', 15, 2);
            $table->decimal('step', 15, 2);
            $table->decimal('max_pct_of_budget', 5, 2);

            // The open-bid leader, frozen at the moment the threshold was crossed. This
            // is who wins if nobody enters the sealed round.
            $table->unsignedBigInteger('leader_team_id')->nullable();
            $table->decimal('leader_amount', 15, 2)->nullable();

            // The round owns its clock. It must not use `auctions.timer_started_at`,
            // because `timerStateFor()` picks the short reset limit whenever
            // `current_bid_team_id` is set — and during a sealed round that is the
            // frozen open leader, so the round would silently get the wrong limit.
            $table->unsignedInteger('timer_seconds')->nullable();
            $table->timestamp('timer_started_at')->nullable();

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();

            $table->decimal('tie_amount', 15, 2)->nullable();
            $table->json('tied_team_ids')->nullable();

            $table->string('resolution', 24)->nullable();

            // The lot is recorded in full so anybody — a protesting manager, a
            // committee — can recompute the winner by hand from these four values and
            // confirm it. That is what makes a random draw defensible.
            $table->string('lot_algorithm', 32)->nullable();
            $table->string('lot_seed', 64)->nullable();
            $table->json('lot_candidates')->nullable();
            $table->unsignedBigInteger('lot_winner_team_id')->nullable();
            $table->timestamp('lot_drawn_at')->nullable();

            $table->unsignedBigInteger('winner_team_id')->nullable();
            $table->decimal('winning_amount', 15, 2)->nullable();

            $table->timestamps();

            // This index IS the idempotency guarantee: a double-clicked "Start Re-bid"
            // collides here instead of creating a fourth round.
            $table->unique(['auction_player_id', 'attempt_no', 'round_number'], 'acbr_player_attempt_round_unique');
            $table->index(['auction_id', 'state'], 'acbr_auction_state_idx');

            $table->foreign('parent_round_id')->references('id')->on('auction_closed_bid_rounds')->nullOnDelete();
            $table->foreign('leader_team_id')->references('id')->on('actual_teams')->nullOnDelete();
            $table->foreign('lot_winner_team_id')->references('id')->on('actual_teams')->nullOnDelete();
            $table->foreign('winner_team_id')->references('id')->on('actual_teams')->nullOnDelete();
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_closed_bid_rounds');
    }
};
