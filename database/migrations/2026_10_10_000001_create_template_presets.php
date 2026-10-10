<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ready-made designs.
 *
 * The layouts live in code (TemplatePresetService / PresetLayouts) so they are
 * versioned with the renderer that draws them. This table only holds what Super
 * Admin decides about each one: on or off, premium or free, and its price. A
 * design with no row is on and free.
 *
 * tournament_templates remembers which design, version and theme a template
 * came from, so a template made from an older version can be offered the update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_presets', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->unsignedInteger('credit_cost')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->string('preset_key', 60)->nullable()->after('is_active');
            $table->string('preset_version', 16)->nullable()->after('preset_key');
            $table->string('preset_theme', 30)->nullable()->after('preset_version');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->dropColumn(['preset_key', 'preset_version', 'preset_theme']);
        });
        Schema::dropIfExists('template_presets');
    }
};
