<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'registration_theme')) {
                $table->json('registration_theme')->nullable()->after('team_registration_form_fields');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('registration_theme');
        });
    }
};
