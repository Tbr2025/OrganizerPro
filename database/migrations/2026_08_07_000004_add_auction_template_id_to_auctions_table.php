<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let an auction name the template it uses.
 *
 * Binding was template → auction only (`auction_templates.auction_id`, set from a select
 * inside the template editor), so from the auction's own screens there was no way to see
 * or change which template it renders with. This is the explicit pick, and it wins over
 * the older binding.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auctions', 'auction_template_id')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedBigInteger('auction_template_id')->nullable()->after('id');
            // nullOnDelete: deleting a template falls the auction back to the default
            // rather than taking its display down.
            $table->foreign('auction_template_id')->references('id')->on('auction_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'auction_template_id')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['auction_template_id']);
            $table->dropColumn('auction_template_id');
        });
    }
};
