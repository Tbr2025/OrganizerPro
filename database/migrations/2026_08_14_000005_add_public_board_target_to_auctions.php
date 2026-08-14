<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which public screens a board plays on.
 *
 * The board went up on the wall AND the ticker together, because there was nowhere to say
 * otherwise. They are different jobs: the wall is the room, the ticker is the stream, and an
 * organizer filling a break in the hall does not necessarily want the broadcast cut away from
 * the lower third — or the reverse.
 *
 * `both` is the default, which is what the two buttons did before this existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('public_board_target', 10)->default('both')->after('public_board');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('public_board_target');
        });
    }
};
