<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE auction_players
            MODIFY COLUMN status ENUM('waiting','on_auction','sold','unsold','closed','skipped')
            NOT NULL DEFAULT 'waiting'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE auction_players
            MODIFY COLUMN status ENUM('waiting','on_auction','sold','unsold','closed')
            NOT NULL DEFAULT 'waiting'
        ");
    }
};
