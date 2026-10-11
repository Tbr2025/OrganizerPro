<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved LED wall setups (timings, stats to rotate, theme) reused across
 * matches and tournaments. organization_id null = a platform preset made by
 * Super Admin, offered to everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('settings');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('led_presets');
    }
};
