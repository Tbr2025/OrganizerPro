<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsor artwork shown on the public screens between lots.
 *
 * Two kinds, deliberately in one table rather than two: they are the same upload, the same
 * ordering and the same on/off switch, and the only thing that differs is where the image is
 * drawn. A `slide` fills a whole turn of the reel; a `sponsor` sits in the strip along the
 * bottom of every slide. Splitting them would mean two screens and two upload flows for one
 * job an organizer thinks of as "our sponsors".
 *
 * Scoped to an auction, not a tournament: sponsorship is sold per event, and the same
 * tournament can run an auction for one sponsor and a later one for another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();

            // `slide` fills a turn of the reel; `sponsor` rides the strip under every slide.
            $table->string('kind', 20)->default('slide');

            $table->string('image_path');
            // Shown under the image on a full slide. Nothing is clickable on a wall, so this is
            // a caption rather than a link — the screens it appears on have no pointer.
            $table->string('caption')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The reel reads exactly this: one auction's active artwork, in order.
            $table->index(['auction_id', 'kind', 'is_active', 'sort_order'], 'auction_ads_reel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_ads');
    }
};
