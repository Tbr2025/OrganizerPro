<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayerRelatedTables extends Migration
{
    public function up()
    {
        Schema::create('kit_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('size'); // e.g., S, M, L, XL
            $table->timestamps();
        });

        Schema::create('batting_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('style'); // e.g., Right-hand Bat
            $table->timestamps();
        });

        Schema::create('bowling_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('style'); // e.g., Right-arm fast
            $table->timestamps();
        });

        Schema::create('player_types', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., Batsman, Bowler, All-Rounder
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kit_sizes');
        Schema::dropIfExists('batting_profiles');
        Schema::dropIfExists('bowling_profiles');
        Schema::dropIfExists('player_types');
    }
}
