<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Pool-A, Pool-B, Group 1, etc.
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
    }
};
