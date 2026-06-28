<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_team_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('actual_team_id')->constrained('actual_teams')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->decimal('budget', 12, 2)->default(0); // per-team allocation
            $table->timestamps();

            $table->unique(['auction_id', 'actual_team_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_team_budgets');
    }
};
