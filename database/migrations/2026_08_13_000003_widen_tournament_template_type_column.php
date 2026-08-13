<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tournament_templates.type` was an ENUM, so the list of poster types lived in two places.
 *
 * They had already drifted. `TYPE_RETAINED_WELCOME_CARD` has been a constant, a seeded email
 * template and an option in the designer for months, and the column has never accepted it —
 * saving one fails with "Data truncated for column 'type'", which reads as a corrupt request
 * rather than as a missing migration. The two auction poster types would have been the same
 * story.
 *
 * A string column instead. The set of valid types is enforced in one place — the `in:` rule
 * built from `TournamentTemplate::TYPES` — which is the place that can be changed by adding a
 * constant, the way somebody adding a type expects it to work.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL only. sqlite has no ENUM to begin with — the column is already text there.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE tournament_templates MODIFY type VARCHAR(64) NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        /*
         * Rolling back cannot be a plain re-narrowing: any row holding a type the old enum
         * never had would be truncated to an empty string on the way down, silently destroying
         * templates. Park those on the nearest old type first — a welcome card is at least a
         * player-shaped design — so the rollback loses a label rather than a template.
         */
        DB::table('tournament_templates')
            ->whereIn('type', ['retained_welcome_card', 'auction_poster', 'auction_poster_portrait'])
            ->update(['type' => 'welcome_card']);

        DB::statement(
            "ALTER TABLE tournament_templates MODIFY type ENUM("
            . "'welcome_card','match_poster','match_summary','award_poster',"
            . "'flyer','champions_poster','point_table','fixtures_poster'"
            . ") NOT NULL"
        );
    }
};
