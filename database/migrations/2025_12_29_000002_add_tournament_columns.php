<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->enum('status', ['draft', 'registration', 'active', 'completed'])->default('draft')->after('location');
            $table->foreignId('champion_team_id')->nullable()->constrained('actual_teams')->nullOnDelete()->after('status');
            $table->foreignId('runner_up_team_id')->nullable()->constrained('actual_teams')->nullOnDelete()->after('champion_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['champion_team_id']);
            $table->dropForeign(['runner_up_team_id']);
            $table->dropColumn(['slug', 'status', 'champion_team_id', 'runner_up_team_id']);
        });
    }
};
