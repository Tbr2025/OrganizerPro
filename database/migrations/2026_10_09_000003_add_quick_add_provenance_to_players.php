<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a player record came from. A scorer can add a walk-in player by name
 * and phone mid-match (QuickPlayerService); these record that it happened,
 * and in which match, alongside the existing created_by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // null = the normal registration/admin paths; 'scoring' = quick-add.
            $table->string('created_via', 20)->nullable()->after('created_by');
            $table->foreignId('source_match_id')->nullable()->after('created_via')
                ->constrained('matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_match_id');
            $table->dropColumn('created_via');
        });
    }
};
