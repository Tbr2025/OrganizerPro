<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');

            // Tournament Branding
            $table->string('logo')->nullable();
            $table->string('background_image')->nullable();
            $table->string('flyer_image')->nullable();
            $table->string('primary_color', 7)->default('#1a56db');
            $table->string('secondary_color', 7)->default('#ffffff');

            // Registration Settings
            $table->boolean('player_registration_open')->default(false);
            $table->boolean('team_registration_open')->default(false);
            $table->timestamp('registration_deadline')->nullable();
            $table->unsignedInteger('max_players_per_team')->default(15);
            $table->unsignedInteger('min_players_per_team')->default(11);

            // Fixture Settings
            $table->enum('format', ['group_knockout', 'league', 'knockout'])->default('group_knockout');
            $table->unsignedTinyInteger('number_of_groups')->default(2);
            $table->unsignedTinyInteger('teams_per_group')->default(4);
            $table->unsignedTinyInteger('matches_per_week')->default(4);
            $table->unsignedTinyInteger('number_of_grounds')->default(1);
            $table->boolean('has_quarter_finals')->default(false);
            $table->boolean('has_semi_finals')->default(true);
            $table->boolean('has_third_place')->default(false);
            $table->unsignedTinyInteger('overs_per_match')->default(20);

            // Points Configuration
            $table->unsignedTinyInteger('points_per_win')->default(2);
            $table->unsignedTinyInteger('points_per_tie')->default(1);
            $table->unsignedTinyInteger('points_per_no_result')->default(1);
            $table->unsignedTinyInteger('points_per_loss')->default(0);

            // Notification Settings
            $table->unsignedTinyInteger('match_poster_days_before')->default(3);
            $table->boolean('send_match_reminders')->default(true);
            $table->boolean('send_result_notifications')->default(true);

            // Social/Public Settings
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->json('social_links')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_settings');
    }
};
