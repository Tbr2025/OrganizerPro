<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_custom_fields')) {
            Schema::create('tournament_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->string('label');
                $table->string('type')->default('text'); // text, textarea, number, dropdown, checkbox, date
                $table->json('options')->nullable();       // dropdown option values
                $table->string('section')->default('Basic Information');
                $table->boolean('required')->default(false);
                $table->boolean('visible')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('tournament_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_registrations', 'custom_field_values')) {
                $table->json('custom_field_values')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn('custom_field_values');
        });
        Schema::dropIfExists('tournament_custom_fields');
    }
};
