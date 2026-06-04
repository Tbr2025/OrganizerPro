<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->enum('package_type', ['starter', 'premium', 'enterprise'])->default('starter')->after('name');
            $table->unsignedInteger('max_tournaments')->nullable()->after('package_type');
            $table->boolean('auction_enabled')->default(false)->after('max_tournaments');
            $table->json('auction_modes')->nullable()->after('auction_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['package_type', 'max_tournaments', 'auction_enabled', 'auction_modes']);
        });
    }
};
