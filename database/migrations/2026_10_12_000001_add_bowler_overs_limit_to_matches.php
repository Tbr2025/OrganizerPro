<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Most overs one bowler may bowl in this match. Null = a fifth of the innings,
 * rounded up (the usual limited-overs rule); set it for local playing rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('bowler_overs_limit')->nullable()->after('overs');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('bowler_overs_limit');
        });
    }
};
