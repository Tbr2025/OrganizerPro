<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->foreignId('ground_id')->nullable()->constrained()->nullOnDelete()->after('venue');
            $table->foreignId('tournament_group_id')->nullable()->constrained()->nullOnDelete()->after('tournament_id');
            $table->enum('stage', ['group', 'quarter_final', 'semi_final', 'final', 'third_place', 'league'])->default('group')->after('status');
            $table->unsignedSmallInteger('match_number')->nullable()->after('stage');
            $table->string('poster_image')->nullable()->after('match_number');
            $table->boolean('poster_sent')->default(false)->after('poster_image');
            $table->timestamp('poster_sent_at')->nullable()->after('poster_sent');
            $table->boolean('is_cancelled')->default(false)->after('poster_sent_at');
            $table->string('cancellation_reason')->nullable()->after('is_cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['ground_id']);
            $table->dropForeign(['tournament_group_id']);
            $table->dropColumn([
                'slug',
                'ground_id',
                'tournament_group_id',
                'stage',
                'match_number',
                'poster_image',
                'poster_sent',
                'poster_sent_at',
                'is_cancelled',
                'cancellation_reason'
            ]);
        });
    }
};
