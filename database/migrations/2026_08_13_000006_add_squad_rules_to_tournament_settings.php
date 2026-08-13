<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The squad rules belong to the tournament, and an auction inherits them.
 *
 * Squad size, how many icon players a team keeps and what they cost, the base price a player
 * starts at — these are facts about the competition, not about one auction evening. They lived
 * only on `auctions`, so a tournament running two auctions had to have them typed twice and
 * could disagree with itself, and a team manager's dashboard had no tournament-level rule to
 * quote when no auction was running.
 *
 * `show_amounts` joins them: whether prices appear on public screens is the same kind of
 * decision, made once for the competition.
 *
 * All nullable. Null means "not decided at tournament level", and the auction's own value is
 * used — so adding these columns changes nothing until somebody fills them in.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * `min_players_per_team` and `max_players_per_team` are ALREADY here, and already
         * editable on the tournament's edit screen — they were simply never read by anything in
         * the auction, which carried its own squad size and ignored them. So this adds the four
         * that are missing and the auction's getters below start honouring all six.
         */
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->unsignedInteger('icon_players_per_team')->nullable()->after('max_players_per_team');
            $table->decimal('icon_player_value', 15, 2)->nullable()->after('icon_players_per_team');
            $table->decimal('player_base_value', 15, 2)->nullable()->after('icon_player_value');

            /*
             * Not nullable, and default true.
             *
             * Every screen shows amounts today, so anything else would change what a live
             * tournament looks like the moment this migration runs. A three-state
             * "undecided/on/off" would also mean every reader having to decide what undecided
             * means, and they would not all decide the same way.
             */
            $table->boolean('show_amounts')->default(true)->after('player_base_value');
        });

        Schema::table('auctions', function (Blueprint $table) {
            /*
             * Whether this auction ignores the tournament and uses its own numbers.
             *
             * Defaults to FALSE so a newly created auction inherits — which is the point of the
             * feature. Existing auctions are switched to TRUE below, because they already carry
             * values somebody chose and a migration must not quietly re-rule a running auction.
             */
            $table->boolean('overrides_tournament_rules')->default(false)->after('show_squad_values');
        });

        /*
         * Every auction that exists right now keeps its own numbers, whatever the tournament
         * later says. Inheriting is opt-in for them, from the edit screen, where the difference
         * is visible before it is saved.
         */
        DB::table('auctions')->update(['overrides_tournament_rules' => true]);
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            // min/max_players_per_team pre-date this migration — not ours to drop.
            $table->dropColumn([
                'icon_players_per_team', 'icon_player_value', 'player_base_value', 'show_amounts',
            ]);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('overrides_tournament_rules');
        });
    }
};
