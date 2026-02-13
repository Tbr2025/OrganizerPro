<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->onDelete('cascade');
            $table->json('highlights')->nullable();
            $table->text('commentary')->nullable();
            $table->string('summary_poster')->nullable();
            $table->boolean('poster_sent')->default(false);
            $table->timestamp('poster_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_summaries');
    }
};
