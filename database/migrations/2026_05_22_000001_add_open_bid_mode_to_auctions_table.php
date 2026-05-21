<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->enum('open_bid_mode', ['online', 'offline'])->default('online')->after('bid_type');
            $table->decimal('online_bid_limit_from', 12, 2)->nullable()->after('open_bid_mode');
            $table->decimal('online_bid_limit_to', 12, 2)->nullable()->after('online_bid_limit_from');
            $table->boolean('mode_manually_overridden')->default(false)->after('online_bid_limit_to');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['open_bid_mode', 'online_bid_limit_from', 'online_bid_limit_to', 'mode_manually_overridden']);
        });
    }
};
