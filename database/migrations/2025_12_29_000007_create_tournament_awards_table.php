<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Man of the Match, Best Bowler, etc.
            $table->string('slug')->nullable();
            $table->string('icon')->nullable(); // Optional icon/emoji
            $table->boolean('is_match_level')->default(true); // vs tournament level
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('order')->default(0);
            $table->string('template_image')->nullable(); // Award poster template
            $table->timestamps();

            $table->unique(['tournament_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_awards');
    }
};
