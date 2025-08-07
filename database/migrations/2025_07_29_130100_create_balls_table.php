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
        Schema::create('balls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bowler_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('batsman_id')->nullable()->constrained('players')->nullOnDelete();
            $table->integer('over'); // e.g., 1, 2, 3
            $table->integer('ball_in_over'); // e.g., 1 to 6
            $table->integer('runs')->default(0); // runs scored off the bat
            $table->string('extra_type')->nullable(); // e.g., 'wide', 'no_ball', 'bye'
            $table->integer('extra_runs')->default(0);
            $table->boolean('is_wicket')->default(false);
            $table->string('dismissal_type')->nullable(); // e.g., 'caught', 'bowled'
            $table->foreignId('fielder_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balls');
    }
};
