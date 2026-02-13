<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            // Auto-notification settings
            $table->boolean('auto_send_welcome_cards')->default(true)->after('send_result_notifications');
            $table->boolean('auto_send_flyer_on_registration')->default(true)->after('auto_send_welcome_cards');
            $table->boolean('auto_send_match_summary')->default(true)->after('auto_send_flyer_on_registration');

            // Template references
            $table->unsignedBigInteger('default_welcome_template_id')->nullable()->after('auto_send_match_summary');
            $table->unsignedBigInteger('default_match_template_id')->nullable()->after('default_welcome_template_id');
            $table->unsignedBigInteger('default_summary_template_id')->nullable()->after('default_match_template_id');
            $table->unsignedBigInteger('semi_final_template_id')->nullable()->after('default_summary_template_id');
            $table->unsignedBigInteger('final_template_id')->nullable()->after('semi_final_template_id');

            // WhatsApp contact for share links
            $table->string('whatsapp_contact')->nullable()->after('contact_phone');

            // Calendar-based scheduling
            $table->json('available_days')->nullable()->after('whatsapp_contact');
            $table->json('default_time_slots')->nullable()->after('available_days');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_send_welcome_cards',
                'auto_send_flyer_on_registration',
                'auto_send_match_summary',
                'default_welcome_template_id',
                'default_match_template_id',
                'default_summary_template_id',
                'semi_final_template_id',
                'final_template_id',
                'whatsapp_contact',
                'available_days',
                'default_time_slots',
            ]);
        });
    }
};
