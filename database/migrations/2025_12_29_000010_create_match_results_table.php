<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');

            // Team A Batting
            $table->unsignedSmallInteger('team_a_score')->default(0);
            $table->unsignedTinyInteger('team_a_wickets')->default(0);
            $table->decimal('team_a_overs', 4, 1)->default(0);
            $table->unsignedSmallInteger('team_a_extras')->default(0);

            // Team B Batting
            $table->unsignedSmallInteger('team_b_score')->default(0);
            $table->unsignedTinyInteger('team_b_wickets')->default(0);
            $table->decimal('team_b_overs', 4, 1)->default(0);
            $table->unsignedSmallInteger('team_b_extras')->default(0);

            // Result Details
            $table->text('result_summary')->nullable(); // "Team A won by 5 wickets"
            $table->foreignId('winner_team_id')->nullable()->constrained('actual_teams')->nullOnDelete();
            $table->enum('result_type', ['runs', 'wickets', 'tie', 'no_result', 'super_over', 'dls'])->nullable();
            $table->unsignedSmallInteger('margin')->nullable(); // runs/wickets/balls

            // Toss
            $table->foreignId('toss_won_by')->nullable()->constrained('actual_teams')->nullOnDelete();
            $table->enum('toss_decision', ['bat', 'bowl'])->nullable();

            // Match Summary
            $table->text('match_notes')->nullable();
            $table->string('summary_image')->nullable();
            $table->boolean('summary_sent')->default(false);
            $table->timestamp('summary_sent_at')->nullable();

            $table->timestamps();

            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
