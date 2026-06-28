<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'state')) {
                // Province / state within the player's country (nationality = `country`).
                $table->string('state')->nullable()->after('country');
                $table->boolean('verified_state')->default(false)->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['state', 'verified_state']);
        });
    }
};
