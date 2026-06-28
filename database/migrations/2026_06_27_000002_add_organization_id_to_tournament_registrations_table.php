<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_registrations', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('tournament_id');
                $table->index('organization_id');
                $table->foreign('organization_id')
                    ->references('id')->on('organizations')->onDelete('set null');
            }
        });

        // Backfill from the parent tournament's organization.
        DB::statement('
            UPDATE tournament_registrations tr
            JOIN tournaments t ON tr.tournament_id = t.id
            SET tr.organization_id = t.organization_id
            WHERE tr.organization_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
