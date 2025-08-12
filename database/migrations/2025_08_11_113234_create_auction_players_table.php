<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('auction_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id')->index();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable(); // if pre-assigned
            $table->unsignedInteger('base_price')->default(1000000); // store as integer (e.g. rupees)
            $table->unsignedInteger('retained_price')->nullable();
            $table->enum('status', ['waiting', 'on_auction', 'sold', 'unsold'])->default('waiting');
            $table->unsignedInteger('current_price')->nullable();
            $table->unsignedBigInteger('current_bid_team_id')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('sold_to_team_id')->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->decimal('starting_price', 10, 2);

            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('auction_players');
    }
};
