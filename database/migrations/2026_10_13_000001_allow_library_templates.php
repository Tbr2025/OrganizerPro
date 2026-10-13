<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The template library: poster templates that belong to no tournament.
 *
 * A row with tournament_id NULL is a library template, designed by Super Admin
 * in the same drag editor. A new tournament (and a new season, when asked)
 * starts with COPIES of them, so editing the library changes what the next
 * tournament gets and never what an existing one already has.
 *
 * No tournament can see a library row: every tournament query goes through
 * $tournament->templates(), and every per-template route aborts when the row's
 * tournament_id is not the tournament in the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Library rows have no tournament to fall back to; they cannot survive NOT NULL.
        \Illuminate\Support\Facades\DB::table('tournament_templates')->whereNull('tournament_id')->delete();

        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_id')->nullable(false)->change();
        });
    }
};
