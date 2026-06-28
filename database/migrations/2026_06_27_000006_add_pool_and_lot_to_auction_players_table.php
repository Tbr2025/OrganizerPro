<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_players', 'auction_pool_id')) {
                $table->unsignedBigInteger('auction_pool_id')->nullable()->after('auction_id')->index();
                $table->foreign('auction_pool_id')->references('id')->on('auction_pools')->nullOnDelete();
            }
            if (! Schema::hasColumn('auction_players', 'lot_number')) {
                $table->unsignedInteger('lot_number')->nullable()->after('auction_pool_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            $table->dropForeign(['auction_pool_id']);
            $table->dropColumn(['auction_pool_id', 'lot_number']);
        });
    }
};
