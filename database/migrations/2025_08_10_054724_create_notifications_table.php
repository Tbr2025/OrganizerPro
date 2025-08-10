<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // e.g. App\Notifications\PlayerUpdatedNotification
            $table->morphs('notifiable'); // user-specific (id & type)

            $table->json('data'); // Will hold player_id, updated_by, page, etc.

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Optional index for faster lookups
            $table->index(['notifiable_id', 'notifiable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
