<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the grounds columns as long as the form has always claimed they are.
 *
 * `google_maps_link` and `address` were created as plain `string()` — varchar(255)
 * — while GroundController validated both at `max:500`. So a value between 256
 * and 500 characters passed validation and then died on the insert with
 *   SQLSTATE[22001]: Data too long for column 'google_maps_link'
 * as a bare 500.
 *
 * That is not a hypothetical range: a Google Maps "place" URL carries the
 * coordinates, a `data=!3m2!...` payload and an `entry`/`g_ep` query string, and
 * the one that surfaced this in production was 257 characters — two over the
 * limit. Real links routinely run longer, so the link becomes TEXT rather than a
 * slightly bigger varchar; validation caps it at 2000 so an absurd paste is a
 * validation message instead of a truncation error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grounds', function (Blueprint $table) {
            $table->text('google_maps_link')->nullable()->change();
            $table->string('address', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
         * Narrowing back would truncate anything already saved at the new
         * length, so trim to fit first — an over-long Maps link is useless
         * truncated, and dropping it loses less than a mangled href.
         */
        \DB::table('grounds')
            ->whereNotNull('google_maps_link')
            ->whereRaw('CHAR_LENGTH(google_maps_link) > 255')
            ->update(['google_maps_link' => null]);

        \DB::table('grounds')
            ->whereNotNull('address')
            ->whereRaw('CHAR_LENGTH(address) > 255')
            ->update(['address' => \DB::raw('LEFT(address, 255)')]);

        Schema::table('grounds', function (Blueprint $table) {
            $table->string('google_maps_link', 255)->nullable()->change();
            $table->string('address', 255)->nullable()->change();
        });
    }
};
