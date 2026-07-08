<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_custom_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_custom_fields', 'form')) {
                $table->string('form')->default('player')->after('tournament_id'); // player | team
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_custom_fields', function (Blueprint $table) {
            $table->dropColumn('form');
        });
    }
};
