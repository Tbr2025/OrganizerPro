<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('balls', function (Blueprint $table) {
            // --- Drop the INCORRECT Foreign Keys using the CORRECT names ---
            // !!! IMPORTANT: REPLACE THESE WITH THE EXACT NAMES FOUND IN YOUR DATABASE !!!
            $actualBatsmanFkName = 'balls_batsman_id_foreign'; // <-- REPLACE THIS WITH THE EXACT NAME FOUND IN YOUR DB (e.g., 'fk_balls_batsman_id_constraint')
            $actualBowlerFkName = 'balls_bowler_id_foreign';   // <-- REPLACE THIS WITH THE EXACT NAME FOUND IN YOUR DB (e.g., 'fk_balls_bowler_id_constraint')

            // Attempt to drop the FOREIGN KEY for batsman_id
            if (Schema::hasColumn('balls', 'batsman_id')) {
                try {
                    $table->dropForeign($actualBatsmanFkName);
                } catch (\Exception $e) {
                    // Log if the constraint doesn't exist or the name is wrong
                    Log::warning("Could not drop foreign key '{$actualBatsmanFkName}': " . $e->getMessage());
                }
            }

            // Attempt to drop the FOREIGN KEY for bowler_id
            if (Schema::hasColumn('balls', 'bowler_id')) {
                try {
                    $table->dropForeign($actualBowlerFkName);
                } catch (\Exception $e) {
                    Log::warning("Could not drop foreign key '{$actualBowlerFkName}': " . $e->getMessage());
                }
            }

            // --- Add the CORRECT Foreign Keys to Existing Columns ---
            // These lines add the *new* foreign keys pointing to actual_team_users(id).
            // Laravel will likely generate default names for these new keys (e.g., 'balls_batsman_id_foreign').
            if (Schema::hasColumn('balls', 'batsman_id')) {
                $table->foreign('batsman_id')->references('id')->on('actual_team_users')->onDelete('set null');
            }

            if (Schema::hasColumn('balls', 'bowler_id')) {
                $table->foreign('bowler_id')->references('id')->on('actual_team_users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balls', function (Blueprint $table) {
            // Drop the foreign keys that were ADDED in the up() method.
            // Use the standard Laravel-generated names for the new FKs.
            if (Schema::hasColumn('balls', 'batsman_id')) {
                $table->dropForeign('balls_batsman_id_foreign');
            }
            if (Schema::hasColumn('balls', 'bowler_id')) {
                $table->dropForeign('balls_bowler_id_foreign');
            }
        });
    }
};