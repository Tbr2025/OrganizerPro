<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            // Player-requested profile edits awaiting admin approval (field => new value).
            if (! Schema::hasColumn('tournament_registrations', 'pending_changes')) {
                $table->json('pending_changes')->nullable();
            }
            if (! Schema::hasColumn('tournament_registrations', 'pending_changes_submitted_at')) {
                $table->timestamp('pending_changes_submitted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn(['pending_changes', 'pending_changes_submitted_at']);
        });
    }
};
