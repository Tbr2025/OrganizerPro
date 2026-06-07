<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_posters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50); // match_poster, match_summary, award_poster, etc.
            $table->string('image_path');
            $table->string('label')->nullable(); // e.g. "Team A vs Team B" or "Player Name - Award"
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_posters');
    }
};
