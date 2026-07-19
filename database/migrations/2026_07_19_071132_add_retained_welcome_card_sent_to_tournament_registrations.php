<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->boolean('retained_welcome_card_sent')->default(false)->after('welcome_card_sent_at');
            $table->timestamp('retained_welcome_card_sent_at')->nullable()->after('retained_welcome_card_sent');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn(['retained_welcome_card_sent', 'retained_welcome_card_sent_at']);
        });
    }
};
