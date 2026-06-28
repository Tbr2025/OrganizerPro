<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            if (! Schema::hasColumn('players', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('players', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('players', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('players', 'visa_status')) {
                $table->string('visa_status')->nullable()->after('state'); // work_visa | visit_visa
            }
            if (! Schema::hasColumn('players', 'employer_name')) {
                $table->string('employer_name')->nullable()->after('visa_status');
            }
            if (! Schema::hasColumn('players', 'employer_address')) {
                $table->text('employer_address')->nullable()->after('employer_name');
            }
            if (! Schema::hasColumn('players', 'employer_position')) {
                $table->string('employer_position')->nullable()->after('employer_address');
            }
            if (! Schema::hasColumn('players', 'available_weekends')) {
                $table->boolean('available_weekends')->default(false)->after('employer_position');
            }
            if (! Schema::hasColumn('players', 'played_ys_ipl_s1')) {
                $table->boolean('played_ys_ipl_s1')->default(false)->after('available_weekends');
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'date_of_birth', 'visa_status',
                'employer_name', 'employer_address', 'employer_position',
                'available_weekends', 'played_ys_ipl_s1',
            ]);
        });
    }
};
