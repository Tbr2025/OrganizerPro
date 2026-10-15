<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template presets: Free / Premium, who they are offered to, and the link from a
 * tournament's copy back to the preset it came from.
 *
 * On a preset (tournament_id NULL):
 *  - tier + price: a premium preset costs `price` credits, once per tournament;
 *  - offered_to: organization ids a global preset is offered to (NULL = all);
 *  - design_version: bumped on every design save, so copies know they are behind.
 *
 * On a tournament's copy:
 *  - source_preset_id / source_version: which preset, and which version of it;
 *  - bg_only: a copy of a FREE preset — only its background can be changed;
 *  - customized / bg_changed: edited since it was copied, so an update from the
 *    preset never overwrites the edit silently.
 *
 * Existing rows: every preset is Free and offered to all; existing tournament
 * templates have no source and stay fully editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->string('tier', 10)->default('free')->after('is_active');
            $table->unsignedInteger('price')->default(0)->after('tier');
            $table->json('offered_to')->nullable()->after('price');
            $table->unsignedInteger('design_version')->default(1)->after('offered_to');
            $table->foreignId('source_preset_id')->nullable()->after('design_version')
                ->constrained('tournament_templates')->nullOnDelete();
            $table->unsignedInteger('source_version')->nullable()->after('source_preset_id');
            $table->boolean('bg_only')->default(false)->after('source_version');
            $table->boolean('customized')->default(false)->after('bg_only');
            $table->boolean('bg_changed')->default(false)->after('customized');
        });

        $now = now();
        DB::table('credit_features')->insertOrIgnore([
            'key' => 'template_background', 'name' => 'Change a template background',
            'description' => 'Each new background on a free design (free designs can only change their background).',
            'cost' => 0, 'charge_trigger' => 'per_output', 'scope' => 'tournament', 'is_active' => true, 'sort' => 6,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('credit_features')->where('key', 'template_background')->delete();

        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_preset_id');
            $table->dropColumn(['tier', 'price', 'offered_to', 'design_version', 'source_version', 'bg_only', 'customized', 'bg_changed']);
        });
    }
};
