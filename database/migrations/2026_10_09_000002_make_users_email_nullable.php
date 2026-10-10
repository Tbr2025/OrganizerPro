<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Players on an open tournament register by phone, with email optional, and
 * a player added from the scoring screen has only a name and a phone. Both
 * still need a users row (actual_team_users and players.user_id point at one).
 *
 * The unique index stays: MySQL allows any number of NULLs in a unique
 * column, so two users may both have no email but never share one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible while any user has no email; refuse rather than
        // invent addresses for them.
        if (\DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Users without an email exist; cannot make users.email NOT NULL again.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
