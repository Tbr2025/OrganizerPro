<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->morphs('notifiable');
            $table->enum('type', [
                'welcome_card',
                'flyer',
                'match_poster',
                'match_summary',
                'award_poster',
                'registration_status'
            ]);
            $table->enum('channel', ['email', 'whatsapp_link'])->default('email');
            $table->string('recipient');
            $table->string('image_path')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // morphs() already creates index on notifiable_type and notifiable_id
            $table->index(['tournament_id', 'type']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
