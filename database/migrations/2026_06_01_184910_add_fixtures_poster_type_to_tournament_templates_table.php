<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tournament_templates MODIFY COLUMN `type` ENUM('welcome_card','match_poster','match_summary','award_poster','flyer','champions_poster','point_table','fixtures_poster') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tournament_templates MODIFY COLUMN `type` ENUM('welcome_card','match_poster','match_summary','award_poster','flyer','champions_poster','point_table') NOT NULL");
    }
};
