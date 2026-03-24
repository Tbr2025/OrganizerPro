<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE matches MODIFY COLUMN stage ENUM('group', 'quarter_final', 'semi_final', 'final', 'third_place', 'league', 'qualifier_1', 'eliminator', 'qualifier_2') DEFAULT 'group'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE matches MODIFY COLUMN stage ENUM('group', 'quarter_final', 'semi_final', 'final', 'third_place', 'league') DEFAULT 'group'");
    }
};
