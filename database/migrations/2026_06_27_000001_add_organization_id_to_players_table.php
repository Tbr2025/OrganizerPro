<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id');
                $table->foreign('organization_id')
                    ->references('id')->on('organizations')->onDelete('set null');
            }
        });

        // Backfill from the owning user's organization where available.
        DB::statement('
            UPDATE players p
            JOIN users u ON p.user_id = u.id
            SET p.organization_id = u.organization_id
            WHERE p.user_id IS NOT NULL AND p.organization_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
