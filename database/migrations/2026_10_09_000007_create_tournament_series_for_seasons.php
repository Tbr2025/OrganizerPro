<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seasons. A series groups the seasons of one competition ("Premier League":
 * Season 1, Season 2, …); each season is an ordinary tournament with its own
 * teams, fixtures, registrations and stats. A tournament with no series simply
 * has no seasons — every existing tournament, unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('type')
                ->constrained('tournament_series')->nullOnDelete();
            $table->unsignedSmallInteger('season_number')->nullable()->after('series_id');
            $table->unique(['series_id', 'season_number'], 'tournaments_series_season_unique');
        });
    }

    public function down(): void
    {
        // The foreign key leans on the unique index: drop the key first.
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
        });
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropUnique('tournaments_series_season_unique');
            $table->dropColumn(['series_id', 'season_number']);
        });

        Schema::dropIfExists('tournament_series');
    }
};
