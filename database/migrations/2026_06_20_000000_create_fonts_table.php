<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // Display + CSS family name, e.g. "Poppins"
            $table->string('slug')->unique();      // Lowercase key, e.g. "poppins"
            $table->enum('source', ['google', 'custom'])->default('google');
            // variants: [{ "weight": 400, "style": "normal", "file": "google/poppins-400.ttf" }, ...]
            $table->json('variants');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonts');
    }
};
