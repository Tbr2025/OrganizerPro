<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         // Modify enum column to add 'closed'
        DB::statement("
            ALTER TABLE auction_players 
            MODIFY COLUMN status ENUM('waiting','on_auction','sold','unsold','closed') 
            NOT NULL DEFAULT 'waiting'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::statement("
            ALTER TABLE auction_players 
            MODIFY COLUMN status ENUM('waiting','on_auction','sold','unsold') 
            NOT NULL DEFAULT 'waiting'
        ");
    }
};
