<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'welcome_card',
                'match_poster',
                'match_summary',
                'award_poster',
                'flyer',
                'champions_poster',
                'point_table'
            ]);
            $table->string('name');
            $table->string('background_image')->nullable();
            $table->longText('layout_json')->nullable();
            $table->json('placeholders')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tournament_id', 'type']);
            $table->index(['tournament_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_templates');
    }
};
