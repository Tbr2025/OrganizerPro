<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Append-only journal of every reversible auction action, with a snapshot of
     * the state that action replaced. Undo pops the newest un-undone row and
     * restores its payload — this is what makes a wrong-team click during a live
     * auction recoverable.
     */
    public function up(): void
    {
        Schema::create('auction_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->unsignedBigInteger('auction_player_id')->nullable();
            // bid | sell | pass | skip | allot
            $table->string('action', 32);
            // Snapshot of the state this action overwrote, for reversal.
            $table->json('payload')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('undone_by')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            // Undo always asks: newest un-undone action for this auction.
            $table->index(['auction_id', 'undone_at', 'id'], 'auction_action_logs_undo_idx');
            $table->foreign('auction_player_id')->references('id')->on('auction_players')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('undone_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_action_logs');
    }
};
