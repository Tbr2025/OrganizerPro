<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delete saved welcome_card and retained_welcome_card email templates
     * so all tournaments fall back to the updated code defaults.
     */
    public function up(): void
    {
        DB::table('email_templates')
            ->whereIn('type', ['welcome_card', 'retained_welcome_card'])
            ->delete();
    }

    /**
     * No rollback — the new code defaults will be used going forward.
     */
    public function down(): void
    {
        //
    }
};
