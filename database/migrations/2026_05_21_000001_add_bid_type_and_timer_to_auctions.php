<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->enum('bid_type', ['open', 'closed'])->default('open')->after('bid_rules');
            $table->unsignedInteger('bid_timer_seconds')->default(30)->after('bid_type');
            $table->unsignedInteger('bid_timer_reset_seconds')->default(15)->after('bid_timer_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['bid_type', 'bid_timer_seconds', 'bid_timer_reset_seconds']);
        });
    }
};
