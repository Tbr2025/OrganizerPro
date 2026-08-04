<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Per-auction control over outbound player mail.
     *
     * Every auction email was sent synchronously inside the request — `Mail::to()->send()`
     * on the sell path — so each sale blocked on SMTP while the room waited, and a
     * rehearsal run emailed real players. These settings let the organizer hold that mail
     * until the auction is over, turn it off entirely, or suppress it for a test run.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true)->after('amount_unit_label');
            }
            if (! Schema::hasColumn('auctions', 'email_test_mode')) {
                // Nothing leaves the building: mail is recorded and skipped.
                $table->boolean('email_test_mode')->default(false)->after('notifications_enabled');
            }
            if (! Schema::hasColumn('auctions', 'email_dispatch')) {
                // deferred = held in the outbox and released when the auction completes.
                $table->enum('email_dispatch', ['immediate', 'deferred'])
                    ->default('deferred')
                    ->after('email_test_mode');
            }
            if (! Schema::hasColumn('auctions', 'emails_flushed_at')) {
                $table->timestamp('emails_flushed_at')->nullable()->after('email_dispatch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['notifications_enabled', 'email_test_mode', 'email_dispatch', 'emails_flushed_at']);
        });
    }
};
