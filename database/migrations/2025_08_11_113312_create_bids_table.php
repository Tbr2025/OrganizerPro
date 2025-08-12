<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id')->index();
            $table->unsignedBigInteger('auction_player_id')->index();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable(); // who placed bid (team manager)
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->foreign('auction_player_id')->references('id')->on('auction_players')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('bids');
    }
};
