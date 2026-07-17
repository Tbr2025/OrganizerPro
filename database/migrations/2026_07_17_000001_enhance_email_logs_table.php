<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            // Change status from enum to string to support pending/sent/failed/bounced
            $table->string('status', 20)->default('sent')->change();

            $table->longText('body_html')->nullable()->after('error_message');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('body_html');

            $table->index('to');
            $table->index('mailable_class');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['to']);
            $table->dropIndex(['mailable_class']);
            $table->dropColumn(['body_html', 'retry_count']);
        });

        // Revert status back to enum
        DB::statement("ALTER TABLE email_logs MODIFY status ENUM('sent','failed') DEFAULT 'sent'");
    }
};
