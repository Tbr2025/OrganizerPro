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
        Schema::create('auction_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['live_display', 'sold_display', 'player_card'])->default('live_display');
            $table->string('background_image')->nullable();
            $table->string('sold_badge_image')->nullable();
            $table->integer('canvas_width')->default(1601);
            $table->integer('canvas_height')->default(910);

            // Element positions stored as JSON
            $table->json('element_positions')->nullable();

            // Quick access fields for common elements (in pixels)
            $table->json('player_image_pos')->nullable();  // {top, left, width, height}
            $table->json('player_name_pos')->nullable();   // {top, left, fontSize}
            $table->json('player_role_pos')->nullable();
            $table->json('batting_style_pos')->nullable();
            $table->json('bowling_style_pos')->nullable();
            $table->json('current_bid_pos')->nullable();
            $table->json('bid_label_pos')->nullable();
            $table->json('stats_matches_pos')->nullable();
            $table->json('stats_runs_pos')->nullable();
            $table->json('stats_wickets_pos')->nullable();
            $table->json('sold_badge_pos')->nullable();
            $table->json('team_logo_pos')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_templates');
    }
};
