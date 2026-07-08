<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_organizer')) {
            Schema::create('tournament_organizer', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['tournament_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_organizer');
    }
};
