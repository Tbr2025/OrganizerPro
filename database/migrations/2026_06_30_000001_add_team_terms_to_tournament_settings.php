<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'team_terms_and_conditions_content')) {
                $table->text('team_terms_and_conditions_content')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('team_terms_and_conditions_content');
        });
    }
};
