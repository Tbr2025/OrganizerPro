<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the wall shows while bidding is private.
 *
 * The sealed overlay was built from whatever logos the auction already had and two fixed English
 * sentences. That is the right default and the wrong ceiling: an organizer running a branded
 * evening wants their own mark and their own words on the one screen the whole hall is looking at
 * for minutes at a time, and a tournament that does not run in English cannot use the sentences
 * at all.
 *
 * All nullable — every auction keeps exactly what it shows today until somebody sets one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'sealed_logo')) {
                $table->string('sealed_logo')->nullable()->after('waiting_background_image');
            }

            if (! Schema::hasColumn('auctions', 'sealed_heading')) {
                $table->string('sealed_heading', 80)->nullable()->after('sealed_logo');
            }

            if (! Schema::hasColumn('auctions', 'sealed_message')) {
                $table->string('sealed_message', 160)->nullable()->after('sealed_heading');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            foreach (['sealed_logo', 'sealed_heading', 'sealed_message'] as $column) {
                if (Schema::hasColumn('auctions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
