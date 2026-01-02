<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_award_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->text('remarks')->nullable();
            $table->string('poster_image')->nullable();
            $table->boolean('poster_sent')->default(false);
            $table->timestamp('poster_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'tournament_award_id'], 'unique_match_award');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_awards');
    }
};
