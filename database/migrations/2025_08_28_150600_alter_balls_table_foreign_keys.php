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
        // Drop foreign keys separately with proper error handling
        $this->dropForeignKeyIfExists('balls', 'balls_batsman_id_foreign');
        $this->dropForeignKeyIfExists('balls', 'balls_bowler_id_foreign');

        // Clear orphan references before adding foreign keys
        if (Schema::hasTable('actual_team_users')) {
            \DB::statement('UPDATE balls SET batsman_id = NULL WHERE batsman_id IS NOT NULL AND batsman_id NOT IN (SELECT id FROM actual_team_users)');
            \DB::statement('UPDATE balls SET bowler_id = NULL WHERE bowler_id IS NOT NULL AND bowler_id NOT IN (SELECT id FROM actual_team_users)');
        }

        // Add new foreign keys
        Schema::table('balls', function (Blueprint $table) {
            if (Schema::hasColumn('balls', 'batsman_id')) {
                $table->foreign('batsman_id')->references('id')->on('actual_team_users')->onDelete('set null');
            }

            if (Schema::hasColumn('balls', 'bowler_id')) {
                $table->foreign('bowler_id')->references('id')->on('actual_team_users')->onDelete('set null');
            }
        });
    }

    /**
     * Safely drop a foreign key if it exists
     */
    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        try {
            $fkExists = \DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
            ", [$table, $foreignKey]);

            if (!empty($fkExists)) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                    $blueprint->dropForeign($foreignKey);
                });
                Log::info("Dropped foreign key '{$foreignKey}' from table '{$table}'");
            } else {
                Log::info("Foreign key '{$foreignKey}' does not exist on table '{$table}', skipping drop");
            }
        } catch (\Exception $e) {
            Log::warning("Could not check/drop foreign key '{$foreignKey}': " . $e->getMessage());
        }
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