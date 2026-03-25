<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->string('vice_captain_email')->nullable()->after('vice_captain_name');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn('vice_captain_email');
        });
    }
};
