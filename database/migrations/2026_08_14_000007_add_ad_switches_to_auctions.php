<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the uploaded artwork is actually played.
 *
 * Each piece already has its own on/off, but turning a whole set off meant unticking every row —
 * and an organizer who wants no ads during the closing lots, or the strip but not the slides,
 * was left deleting artwork they had just uploaded. Two switches beside the board controls do
 * what the moment needs without touching what was agreed with the sponsor.
 *
 * Both default true: an auction that has uploaded artwork means to show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('ads_slides_enabled')->default(true)->after('budget_alert_pct');
            $table->boolean('ads_sponsors_enabled')->default(true)->after('ads_slides_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['ads_slides_enabled', 'ads_sponsors_enabled']);
        });
    }
};
