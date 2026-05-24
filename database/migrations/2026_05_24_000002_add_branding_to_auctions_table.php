<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('background_image')->nullable()->after('closed_bid_starts_at');
            $table->string('auction_logo')->nullable()->after('background_image');
            $table->string('waiting_background_image')->nullable()->after('auction_logo');
            $table->string('primary_color', 7)->nullable()->after('waiting_background_image');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'background_image',
                'auction_logo',
                'waiting_background_image',
                'primary_color',
                'secondary_color',
            ]);
        });
    }
};
