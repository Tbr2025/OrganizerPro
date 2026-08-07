<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a template be authored as raw HTML + CSS instead of dragged elements.
 *
 * `render_mode` is a separate axis from `type`: `type` answers *which screen* this is
 * for (live_display / sold_display / player_card), render mode answers *how it was
 * authored*. Folding HTML into the type enum would break the live wall's lookup, split
 * the per-type `is_default` bucket in two, and need an `ALTER … MODIFY enum` again for
 * every future mode.
 *
 * A plain string, not an enum — the enum directly above it is the cautionary tale.
 * Values are validated in PHP against AuctionTemplate::RENDER_*.
 *
 * `html_css` is stored apart from `html_body` so the stylesheet can go into a nonce'd
 * <style> while the body stays script-free under the page's CSP.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('auction_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_templates', 'render_mode')) {
                // Default makes every existing row correct with no consumer changes.
                $table->string('render_mode', 20)->default('positioned')->after('type');
            }
            if (! Schema::hasColumn('auction_templates', 'html_body')) {
                $table->longText('html_body')->nullable();
            }
            if (! Schema::hasColumn('auction_templates', 'html_css')) {
                $table->longText('html_css')->nullable();
            }
            if (! Schema::hasColumn('auction_templates', 'html_body_previous')) {
                // One step of undo. An author who breaks the wall mid-auction is
                // standing in front of an audience.
                $table->longText('html_body_previous')->nullable();
            }
            if (! Schema::hasColumn('auction_templates', 'html_refresh_ms')) {
                $table->unsignedInteger('html_refresh_ms')->default(2000);
            }
            if (! Schema::hasColumn('auction_templates', 'html_transparent_bg')) {
                $table->boolean('html_transparent_bg')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_templates', function (Blueprint $table) {
            foreach ([
                'render_mode', 'html_body', 'html_css',
                'html_body_previous', 'html_refresh_ms', 'html_transparent_bg',
            ] as $column) {
                if (Schema::hasColumn('auction_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
