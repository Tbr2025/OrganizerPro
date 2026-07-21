<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('page');           // registration, player_dashboard, team_manager_dashboard
            $table->string('position');       // top, bottom
            $table->string('display_type')->default('static'); // static, slider
            $table->string('image_path');
            $table->string('aspect_ratio')->default('landscape'); // wide, landscape, portrait, square
            $table->string('link_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'page', 'position', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_banners');
    }
};
