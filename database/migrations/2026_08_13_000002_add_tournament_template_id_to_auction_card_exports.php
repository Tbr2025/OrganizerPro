<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which design an export is rendering: the LED wall's card, or a poster from the drag editor.
 *
 * Null keeps the original behaviour — a screenshot of the wall, so the hall and the download
 * cannot disagree. Set, the export renders the auction poster template of that id with GD
 * instead, which is both a different shape (landscape or portrait, not the wall's 1601x910)
 * and enormously faster: no browser is started per player.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_card_exports', function (Blueprint $table) {
            // nullOnDelete rather than cascade: deleting a template must not take an operator's
            // finished zip with it.
            $table->foreignId('tournament_template_id')->nullable()->after('with_result')
                ->constrained('tournament_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auction_card_exports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_template_id');
        });
    }
};
