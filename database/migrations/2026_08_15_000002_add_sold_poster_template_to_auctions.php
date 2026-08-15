<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which card a sold player is emailed.
 *
 * The attachment was whichever auction poster happened to be the tournament's default. A
 * tournament often has more than one — a portrait for social, a landscape for the wall, an older
 * one kept for reference — and the organizer had no way to say which of them a player receives.
 *
 * Nullable: unset keeps exactly today's behaviour, the default poster.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'sold_poster_template_id')) {
                $table->unsignedBigInteger('sold_poster_template_id')->nullable()->after('ticker_template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (Schema::hasColumn('auctions', 'sold_poster_template_id')) {
                $table->dropColumn('sold_poster_template_id');
            }
        });
    }
};
