<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizer_assignments')) {
            Schema::create('organizer_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->morphs('assignable'); // assignable_type + assignable_id (Tournament / ActualTeam / Matches)
                $table->timestamps();
                $table->unique(['user_id', 'assignable_type', 'assignable_id'], 'organizer_assignment_unique');
            });
        }

        // Migrate existing tournament-level assignments into the unified table.
        if (Schema::hasTable('tournament_organizer')) {
            $rows = DB::table('tournament_organizer')->get();
            foreach ($rows as $r) {
                DB::table('organizer_assignments')->updateOrInsert(
                    ['user_id' => $r->user_id, 'assignable_type' => \App\Models\Tournament::class, 'assignable_id' => $r->tournament_id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
            Schema::dropIfExists('tournament_organizer');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_assignments');
    }
};
