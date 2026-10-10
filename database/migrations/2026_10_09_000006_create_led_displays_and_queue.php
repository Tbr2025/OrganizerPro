<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LED wall: a display (one private link, any number of screens showing it)
 * and its up-next queue.
 *
 * What is on screen is derived from the queue on every read
 * (LedQueueService::tick), never held by a browser, so every screen on one
 * display shows the same panel and a screen that reloads picks up mid-rotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('name', 100);
            // The private link. Unguessable; regenerating it revokes every old screen.
            $table->string('token', 64)->unique();
            $table->string('mode', 10)->default('auto');   // auto | manual
            $table->boolean('frozen')->default(false);     // keep the current panel up
            $table->json('settings')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('led_display_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('led_display_id')->constrained()->cascadeOnDelete();
            $table->string('panel', 40);
            // Frozen at enqueue time: what the operator approves is what airs.
            $table->json('payload')->nullable();
            $table->string('summary', 255);                // one-line preview for the operator
            // queued → showing → shown, or skipped; held = waiting for the operator.
            $table->string('status', 10)->default('queued');
            $table->string('source', 10)->default('auto'); // auto | manual
            $table->boolean('pinned')->default(false);
            $table->integer('position')->default(0);
            $table->unsignedSmallInteger('show_seconds')->default(8);
            // Earliest moment it may air: the operator's window to skip it.
            $table->timestamp('air_after')->nullable();
            // The ball that produced it; an undo of that ball skips it.
            $table->unsignedBigInteger('ball_id')->nullable()->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['led_display_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('led_display_queue');
        Schema::dropIfExists('led_displays');
    }
};
