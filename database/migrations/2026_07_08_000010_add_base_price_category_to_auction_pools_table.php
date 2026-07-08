<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_pools', 'base_price')) {
                $table->decimal('base_price', 15, 2)->nullable()->after('capacity');
            }
            if (! Schema::hasColumn('auction_pools', 'category')) {
                $table->string('category')->nullable()->after('base_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            foreach (['base_price', 'category'] as $col) {
                if (Schema::hasColumn('auction_pools', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
