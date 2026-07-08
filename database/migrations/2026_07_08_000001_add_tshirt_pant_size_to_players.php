<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'tshirt_size')) {
                $table->string('tshirt_size', 50)->nullable();
            }
            if (! Schema::hasColumn('players', 'pant_size')) {
                $table->string('pant_size', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['tshirt_size', 'pant_size']);
        });
    }
};
