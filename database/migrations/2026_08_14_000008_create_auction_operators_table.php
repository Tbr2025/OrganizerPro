<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who may run THIS auction, and what they may do in it.
 *
 * The permissions already split the panel three ways — observe the board, enter and unwind bids,
 * sell and end — and the routes enforce that split. What they cannot express is *which* auction:
 * a permission is global, so anyone able to call one evening's lots could call every auction in
 * the organization, including one they have nothing to do with.
 *
 * This is the missing half. A row grants a named user a set of abilities on one auction, and the
 * middleware refuses anything outside it. Organizers and admins are unaffected — they already
 * own their auctions and are not narrowed by an absent row, which is what keeps this from
 * locking the people who set it up out of their own event.
 *
 * Abilities as JSON rather than four booleans: the set will grow (allotment, ads, closed bids
 * have all been asked about), and adding a column per capability means a migration for each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Named abilities — see AuctionOperator::abilities().
            $table->json('abilities');

            $table->timestamps();

            // One row per person per auction: two rows would mean two answers to "what may they
            // do", and the wrong one being read is a permission bug nobody would see coming.
            $table->unique(['auction_id', 'user_id'], 'auction_operators_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_operators');
    }
};
