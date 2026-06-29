<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_players', 'is_retained')) {
                // Per-auction flag: a pre-kept (retained) pool member that is NOT
                // auctioned until "merged" into the run.
                $table->boolean('is_retained')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            $table->dropColumn('is_retained');
        });
    }
};
