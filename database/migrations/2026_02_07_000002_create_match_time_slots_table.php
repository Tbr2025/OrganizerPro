<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('ground_id')->nullable()->constrained()->onDelete('set null');
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'ground_id', 'slot_date', 'start_time'], 'unique_slot');
            $table->index(['tournament_id', 'slot_date']);
            $table->index(['tournament_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_time_slots');
    }
};
