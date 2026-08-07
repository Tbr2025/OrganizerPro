<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which ticker template this auction uses.
 *
 * Mirrors `auction_template_id` (the LED wall's choice) rather than reusing it: an organizer
 * runs the wall and the ticker as two different screens in the same room, so one column
 * cannot serve both. Nullable — with nothing chosen the ticker keeps its built-in look, which
 * is what every existing auction expects.
 *
 * nullOnDelete, so deleting a template downgrades the ticker to its default rather than
 * cascading the auction away.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auctions', 'ticker_template_id')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->foreignId('ticker_template_id')
                ->nullable()
                ->after('auction_template_id')
                ->constrained('auction_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'ticker_template_id')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticker_template_id');
        });
    }
};
