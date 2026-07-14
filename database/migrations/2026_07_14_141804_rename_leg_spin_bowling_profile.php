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
        DB::table('bowling_profiles')
            ->where('style', 'LIKE', '%Leg Spin%')
            ->update(['style' => 'Right Arm Leg Spin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bowling_profiles')
            ->where('style', 'Right Arm Leg Spin')
            ->update(['style' => 'Leg Spin']);
    }
};
