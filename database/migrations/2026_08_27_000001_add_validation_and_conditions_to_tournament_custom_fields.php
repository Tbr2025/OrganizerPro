<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validation rules, conditional visibility and help text for custom registration fields.
 *
 * Every column is nullable with no default behaviour of its own, deliberately: a field that
 * predates this migration reads back `validation = null`, `conditions = null` and
 * `condition_match = null`, and the model treats that as "no extra rules, always visible" —
 * which is exactly how those fields behave today. Existing tournaments must not change because
 * the builder gained features.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_custom_fields', function (Blueprint $table) {
            // Curated rules only — {min, max, minlength, maxlength, pattern, file_types, ...}.
            // Never a raw Laravel rule string: those can reach the database (`unique:users,...`).
            $table->json('validation')->nullable()->after('options');

            // [{field, operator, value}] — `field` is another custom field's id or a core form key.
            $table->json('conditions')->nullable()->after('validation');

            // How the conditions above combine: all = AND, any = OR, none = NOT.
            // NULL means the field has no conditions and is always shown.
            $table->string('condition_match', 8)->nullable()->after('conditions');

            // Shown under the input on the public form.
            $table->string('help_text', 500)->nullable()->after('condition_match');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_custom_fields', function (Blueprint $table) {
            $table->dropColumn(['validation', 'conditions', 'condition_match', 'help_text']);
        });
    }
};
