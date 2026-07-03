<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            // Admin per-field verification: stores the set of verified field keys.
            if (! Schema::hasColumn('tournament_registrations', 'verified_fields')) {
                $table->json('verified_fields')->nullable();
            }
            // Digitally-signed consent capture.
            if (! Schema::hasColumn('tournament_registrations', 'consent_name')) {
                $table->string('consent_name')->nullable();
            }
            if (! Schema::hasColumn('tournament_registrations', 'consent_signed_at')) {
                $table->timestamp('consent_signed_at')->nullable();
            }
            if (! Schema::hasColumn('tournament_registrations', 'consent_ip')) {
                $table->string('consent_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('tournament_registrations', 'consent_snapshot')) {
                $table->text('consent_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn(['verified_fields', 'consent_name', 'consent_signed_at', 'consent_ip', 'consent_snapshot']);
        });
    }
};
