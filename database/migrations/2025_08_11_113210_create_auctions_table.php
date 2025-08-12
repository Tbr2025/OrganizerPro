<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "YouSelects IPL 2025"
            $table->unsignedBigInteger('tournament_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->enum('status', ['scheduled', 'running', 'paused', 'completed'])->default('scheduled');
            $table->decimal('base_price', 10, 2)->default(1.0);
            $table->decimal('max_bid_per_player', 10, 2)->default(6.0);
            $table->decimal('max_budget_per_team', 10, 2)->default(100.0);
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('auctions');
    }
};
