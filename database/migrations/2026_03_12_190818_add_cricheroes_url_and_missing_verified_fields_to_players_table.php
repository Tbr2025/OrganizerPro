<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // CricHeroes Profile URL
            $table->string('cricheroes_profile_url')->nullable()->after('cricheroes_number_full');
            $table->boolean('verified_cricheroes_profile_url')->default(false)->after('cricheroes_profile_url');

            // Missing verified fields
            if (!Schema::hasColumn('players', 'verified_location_id')) {
                $table->boolean('verified_location_id')->default(false)->after('location_id');
            }
            if (!Schema::hasColumn('players', 'verified_total_matches')) {
                $table->boolean('verified_total_matches')->default(false)->after('total_matches');
            }
            if (!Schema::hasColumn('players', 'verified_total_runs')) {
                $table->boolean('verified_total_runs')->default(false)->after('total_runs');
            }
            if (!Schema::hasColumn('players', 'verified_total_wickets')) {
                $table->boolean('verified_total_wickets')->default(false)->after('total_wickets');
            }
            if (!Schema::hasColumn('players', 'verified_travel_date_from')) {
                $table->boolean('verified_travel_date_from')->default(false)->after('travel_date_from');
            }
            if (!Schema::hasColumn('players', 'verified_travel_date_to')) {
                $table->boolean('verified_travel_date_to')->default(false)->after('travel_date_to');
            }
            if (!Schema::hasColumn('players', 'verified_team_name_ref')) {
                $table->boolean('verified_team_name_ref')->default(false)->after('team_name_ref');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'cricheroes_profile_url',
                'verified_cricheroes_profile_url',
            ]);

            // Only drop if they exist
            $columns = [
                'verified_location_id',
                'verified_total_matches',
                'verified_total_runs',
                'verified_total_wickets',
                'verified_travel_date_from',
                'verified_travel_date_to',
                'verified_team_name_ref',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('players', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
