<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per "render me these players' cards" request, so the work can report on itself.
 *
 * A card is a headless-browser screenshot — seconds each, not milliseconds — so a zip of a
 * pool was a request that sat silent for minutes with nothing to show the operator, and a zip
 * of a whole auction was one that nginx cut off before it finished. Neither is fixable inside
 * a single request: progress has to be readable from somewhere OTHER than the connection doing
 * the work, and the work has to survive being longer than any one request may live.
 *
 * Hence a row. The request creates it and returns immediately; a queued job renders in chunks
 * and counts up here; the page polls this and shows a bar; the download comes afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_card_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            // Who asked. Nullable so deleting a user does not destroy an export in flight.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /*
             * The handle the browser polls with.
             *
             * Unguessable rather than the row id, because progress and download are read by a
             * page that already knows the auction — an incrementing id would let anyone with
             * access to one auction walk every other export in the table.
             */
            $table->uuid('token')->unique();

            $table->boolean('with_result')->default(false);

            /*
             * The auction_player ids to render, resolved ONCE when the export is created.
             *
             * Not re-derived per chunk: a pool edited while a 200-card export is running would
             * otherwise change what "the rest" means halfway through, and the total the
             * operator is watching would stop meaning anything.
             */
            $table->json('auction_player_ids');

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);

            // queued | running | done | failed
            $table->string('status', 20)->default('queued');

            /*
             * Why it failed, or which card did — in the operator's words, not the log's.
             * Every card tends to fail for the same reason (the renderer cannot reach the
             * page), and sending somebody to read a log to learn that is not an answer.
             */
            $table->text('message')->nullable();

            $table->string('path')->nullable();
            $table->string('filename')->nullable();

            $table->timestamps();

            // The sweep that deletes finished exports and their zips reads this.
            $table->index(['auction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_card_exports');
    }
};
