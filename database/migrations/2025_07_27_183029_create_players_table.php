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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->string('team_name_ref')->nullable(); // For reference, if needed
            $table->string('name');
            $table->boolean('verified_name')->default(false);

            $table->string('email')->unique(); // Added email field, should be unique
            $table->boolean('verified_email')->default(false);

            $table->string('image_path')->nullable(); // stores file path
            $table->string('image')->nullable(); // Made nullable based on form (Optional)
            $table->longText('layout_json')->nullable();
            $table->string('welcome_image_path')->nullable();
            $table->boolean('verified_image_path')->default(false);

            // Mobile Number fields
            $table->string('mobile_country_code', 10); // e.g., +91, +1
            $table->string('mobile_national_number', 20)->nullable();; // e.g., 9876543210
            $table->string('mobile_number_full', 30); // Store the full number for display/convenience
            $table->boolean('verified_mobile_number_full')->default(false); // ✅ Add this line

            // Cricheroes Number fields (optional)
            $table->string('cricheroes_country_code', 10)->nullable();
            $table->string('cricheroes_national_number', 20)->nullable();
            $table->string('cricheroes_number_full', 30)->nullable();
            $table->boolean('verified_cricheroes_number_full')->default(false);

            $table->string('jersey_name')->nullable(); // Made nullable based on form (Optional)
            $table->boolean('verified_jersey_name')->default(false);

            $table->foreignId('kit_size_id')->nullable()->constrained('kit_sizes')->onDelete('set null');
            $table->boolean('verified_kit_size_id')->default(false);

            $table->foreignId('batting_profile_id')->nullable()->constrained('batting_profiles')->onDelete('set null');
            $table->boolean('verified_batting_profile_id')->default(false);

            $table->foreignId('bowling_profile_id')->nullable()->constrained('bowling_profiles')->onDelete('set null');
            $table->boolean('verified_bowling_profile_id')->default(false);

            $table->foreignId('player_type_id')->nullable()->constrained('player_types')->onDelete('set null');
            $table->boolean('verified_player_type_id')->default(false);

            $table->boolean('is_wicket_keeper')->default(false);
            $table->boolean('verified_is_wicket_keeper')->default(false);

            $table->boolean('transportation_required')->default(false);
            $table->boolean('verified_transportation_required')->default(false);

            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('verified_team_id')->default(false);

            // Player Status for approval
            // 'pending', 'approved', 'rejected'
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('email_verified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            // Adding a field for who approved/rejected the player
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('welcome_email_sent_at')->nullable();
            $table->boolean('no_travel_plan')->default(false);
            $table->date('travel_date_from')->nullable();
            $table->date('travel_date_to')->nullable();
            $table->boolean('verified_no_travel_plan')->default(false);

            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
