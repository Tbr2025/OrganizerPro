<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presets come in two kinds: 'wall' (the LED wall's timings, rotation and
 * theme — every existing preset) and 'ticker' (a saved design for the
 * live-video score ticker: colours, size, position, parts shown).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('led_presets', function (Blueprint $table) {
            $table->string('kind', 10)->default('wall')->after('organization_id');
            $table->index(['kind', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::table('led_presets', function (Blueprint $table) {
            $table->dropIndex(['kind', 'organization_id']);
            $table->dropColumn('kind');
        });
    }
};
