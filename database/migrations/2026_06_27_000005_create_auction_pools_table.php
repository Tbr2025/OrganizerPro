<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable(); // e.g. 50 / 100; null = unlimited
            $table->enum('order_mode', ['sequential', 'random', 'odd_even', 'manual'])->default('sequential');
            $table->unsignedInteger('sequence')->default(0); // pool order within the auction
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->boolean('is_unsold_pool')->default(false); // dedicated bucket for re-pooling unsold
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_pools');
    }
};
