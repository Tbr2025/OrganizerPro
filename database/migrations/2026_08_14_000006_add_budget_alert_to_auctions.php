<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warn a team when their purse is running down, and remember that they were told.
 *
 * A manager watching one player at a time does not feel the shape of their own budget until it
 * is too late to change how they bid — and the organizer hears about it afterwards, as a
 * complaint. The threshold is a percentage rather than an amount because teams can carry
 * different allocations, and "70% left" means the same thing to all of them where "35M left"
 * does not.
 *
 * Null switches it off entirely, which is the default: an auction that has never wanted this
 * must not start warning people because a column appeared.
 *
 * The acknowledgement is per team per auction. It is a table rather than a column on
 * `actual_teams` because a team can play in several auctions and being warned in one says
 * nothing about another — and rather than a session flag because the point is that it survives
 * a manager closing their laptop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedTinyInteger('budget_alert_pct')->nullable()->after('public_board_target');
        });

        Schema::create('auction_budget_acks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actual_team_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['auction_id', 'actual_team_id'], 'auction_budget_acks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_budget_acks');

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('budget_alert_pct');
        });
    }
};
