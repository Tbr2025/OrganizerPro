<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'available_saturday')) {
                $table->boolean('available_saturday')->default(false)->after('available_weekends');
            }
            if (! Schema::hasColumn('players', 'available_sunday')) {
                $table->boolean('available_sunday')->default(false)->after('available_saturday');
            }
        });

        // Back-fill the new per-day flags from the legacy combined flag.
        if (Schema::hasColumn('players', 'available_weekends')) {
            \DB::table('players')->where('available_weekends', true)->update([
                'available_saturday' => true,
                'available_sunday' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['available_saturday', 'available_sunday']);
        });
    }
};
