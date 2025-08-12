<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->enum('player_mode', ['retained', 'normal', 'not_selected'])->default('normal')->after('status');
            // optionally store a numeric retained_value
            $table->unsignedInteger('retained_value')->nullable()->comment('in base currency (e.g. rupees)')->after('player_mode');
        });
    }
    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('player_mode');
            $table->dropColumn('retained_value');
        });
    }
};
