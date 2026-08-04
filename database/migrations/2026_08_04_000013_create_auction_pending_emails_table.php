<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Outbox for auction mail that is held until the auction finishes.
     *
     * Laravel's own queue cannot express "send when this auction ends" — that moment is
     * not known when the mail is raised. So each message is recorded here during the
     * auction and released in one pass afterwards, which also keeps poster generation
     * (the expensive part of a welcome card) off the live auction's critical path.
     */
    public function up(): void
    {
        Schema::create('auction_pending_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->unsignedBigInteger('auction_player_id')->nullable();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('actual_team_id')->nullable();
            // welcome_card | sold | unsold
            $table->string('type', 32);
            // Anything the sender needs that is not derivable at send time (e.g. price).
            $table->json('payload')->nullable();
            // pending | sent | skipped | failed
            $table->string('status', 16)->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // "What is still waiting for this auction?" on every flush and page load.
            $table->index(['auction_id', 'status'], 'auction_pending_emails_auction_status_idx');
            $table->foreign('auction_player_id')->references('id')->on('auction_players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_pending_emails');
    }
};
