<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scorer can end a match by hand — a shortened game, or one abandoned for
 * rain — instead of only by the last ball. Once set, no more balls are taken
 * until scoring is reopened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('scoring_ended_at')->nullable()->after('score_version');
            $table->string('end_reason', 120)->nullable()->after('scoring_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['scoring_ended_at', 'end_reason']);
        });
    }
};
