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
        Schema::create('bounced_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('bounce_type')->default('Permanent'); // Permanent, Transient, Complaint
            $table->string('bounce_subtype')->nullable();
            $table->string('source')->default('sns'); // sns, manual
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bounced_emails');
    }
};
