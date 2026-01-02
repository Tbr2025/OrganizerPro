<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['player', 'team']);

            // For Player Registration
            $table->foreignId('player_id')->nullable()->constrained()->onDelete('cascade');

            // For Team Registration
            $table->string('team_name')->nullable();
            $table->string('team_short_name')->nullable();
            $table->string('team_logo')->nullable();
            $table->string('captain_name')->nullable();
            $table->string('captain_email')->nullable();
            $table->string('captain_phone')->nullable();
            $table->string('vice_captain_name')->nullable();
            $table->string('vice_captain_phone')->nullable();
            $table->text('team_description')->nullable();

            // Common Fields
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // For approved teams - link to actual_team
            $table->foreignId('actual_team_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_registrations');
    }
};
