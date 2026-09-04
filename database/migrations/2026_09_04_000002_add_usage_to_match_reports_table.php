<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each generation actually cost.
 *
 * OpenAI reports the tokens it charged for in every response, so the dashboard can show a real
 * average per post rather than an estimate built from guessed prompt sizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_reports', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->nullable()->after('model');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            // Fractions of a cent per post, so six decimal places rather than two.
            $table->decimal('cost_usd', 10, 6)->nullable()->after('completion_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('match_reports', function (Blueprint $table) {
            $table->dropColumn(['prompt_tokens', 'completion_tokens', 'cost_usd']);
        });
    }
};
