<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            // Null tournament_id = global default used as the fallback for all tournaments.
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // under_review | approved | welcome_card
            $table->string('subject');
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tournament_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
