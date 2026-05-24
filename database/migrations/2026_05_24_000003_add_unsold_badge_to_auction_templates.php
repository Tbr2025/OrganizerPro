<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_templates', function (Blueprint $table) {
            $table->string('unsold_badge_image')->nullable()->after('sold_badge_image');
        });
    }

    public function down(): void
    {
        Schema::table('auction_templates', function (Blueprint $table) {
            $table->dropColumn('unsold_badge_image');
        });
    }
};
