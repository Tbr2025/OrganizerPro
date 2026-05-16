<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('tournament_id');
            $table->unsignedBigInteger('tournament_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->dropColumn('is_global');
            $table->unsignedBigInteger('tournament_id')->nullable(false)->change();
        });
    }
};
