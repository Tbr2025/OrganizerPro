<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->boolean('welcome_card_sent')->default(false)->after('processed_by');
            $table->timestamp('welcome_card_sent_at')->nullable()->after('welcome_card_sent');
            $table->boolean('flyer_sent')->default(false)->after('welcome_card_sent_at');
            $table->timestamp('flyer_sent_at')->nullable()->after('flyer_sent');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_card_sent',
                'welcome_card_sent_at',
                'flyer_sent',
                'flyer_sent_at',
            ]);
        });
    }
};
