<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_appreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('title_line1')->nullable();
            $table->string('title_line2')->nullable();
            $table->string('font_family')->nullable();
            $table->integer('angle')->default(0);
            $table->string('overlay_name')->nullable();
            $table->string('image_path')->nullable();
            $table->string('appreciation_type'); // E.g., "Best Batsman", "Best Bowler"

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_appreciations');
    }
};
