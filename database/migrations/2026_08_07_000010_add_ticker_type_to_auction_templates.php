<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a template describe the broadcast ticker, not just the LED wall.
 *
 * The ticker is the lower-third strip an OBS scene points at. It had no template of any
 * kind, so its look was whatever `public/auction/ticker.blade.php` hard-codes — while the
 * wall next to it was fully authorable. A ticker template is always an HTML one (authored
 * markup + CSS with {tokens}); the positioned editor exists to place elements on a
 * 1601x910 card and does not describe a 90px strip.
 *
 * Raw ALTER rather than the Schema builder, following the precedent set by
 * 2026_06_01_184910 and 2026_05_19_000001: Doctrine cannot modify a MySQL enum in place.
 * That makes this migration MySQL-only, which matches the rest of the file's neighbours.
 */
return new class () extends Migration {
    private const WITH_TICKER = "ENUM('live_display','sold_display','player_card','ticker') NOT NULL DEFAULT 'live_display'";

    private const WITHOUT_TICKER = "ENUM('live_display','sold_display','player_card') NOT NULL DEFAULT 'live_display'";

    public function up(): void
    {
        if (! Schema::hasTable('auction_templates')) {
            return;
        }

        DB::statement('ALTER TABLE auction_templates MODIFY COLUMN `type` ' . self::WITH_TICKER);
    }

    public function down(): void
    {
        if (! Schema::hasTable('auction_templates')) {
            return;
        }

        // Anything still marked `ticker` would fail the narrowed enum, so move it back to a
        // value that exists rather than letting the migration abort half way.
        DB::table('auction_templates')->where('type', 'ticker')->update(['type' => 'live_display']);

        DB::statement('ALTER TABLE auction_templates MODIFY COLUMN `type` ' . self::WITHOUT_TICKER);
    }
};
