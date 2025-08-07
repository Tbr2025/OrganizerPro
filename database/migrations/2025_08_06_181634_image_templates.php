<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('image_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Friendly name for the template
            $table->string('background_image')->nullable(); // Optional preview image
            $table->string('overlay_image_path')->nullable(); // Optional preview image
            $table->longText('layout_json');  // Fabric.js layout JSON
            // New category foreign key
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('image_template_categories')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_templates');
    }
};
