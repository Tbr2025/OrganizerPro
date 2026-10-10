<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pay-per-feature credits.
 *
 * Every balance change is a ledger row; a wallet's balance is only ever moved
 * in the same transaction as the row that explains it (CreditService). Nothing
 * here charges anybody on deploy:
 *  - existing organizations stay on billing_mode 'package' (today's limits);
 *  - every feature is seeded at cost 0 until Super Admin sets a price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // package = the old max_tournaments / auction_enabled limits; credits = pay per feature.
            $table->string('billing_mode', 10)->default('package')->after('auction_modes');
        });

        Schema::create('credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('credit_wallets')->cascadeOnDelete();
            // purchase | usage | refund | adjustment | promo | reversal
            $table->string('type', 12);
            $table->bigInteger('amount');            // + adds credits, - spends them
            $table->bigInteger('balance_after');
            $table->string('feature_key', 40)->nullable();
            $table->nullableMorphs('reference');
            // A retried request never charges twice.
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'id']);
        });

        Schema::create('credit_features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('cost')->default(0);
            // unlock_once: pay once per scope (e.g. per tournament); per_output: pay for each result.
            $table->string('charge_trigger', 12);
            $table->string('scope', 12)->default('tournament');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_feature_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 40);
            $table->nullableMorphs('scope');
            $table->foreignId('ledger_id')->nullable()->constrained('credit_ledger')->nullOnDelete();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'feature_key', 'scope_type', 'scope_id'], 'credit_unlock_unique');
        });

        // Key/value with history: the newest row per key (by effective_from) wins.
        Schema::create('credit_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40);
            $table->text('value')->nullable();
            $table->timestamp('effective_from')->useCurrent();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['key', 'effective_from']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 40)->nullable()->unique();   // null = applied automatically
            $table->string('type', 10);                          // percent | fixed
            $table->unsignedInteger('value');
            // topup | features | feature:<key>
            $table->string('applies_to', 60)->default('features');
            $table->json('organization_ids')->nullable();        // null = everyone
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('per_org_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained('credit_ledger')->nullOnDelete();
            $table->foreignId('topup_request_id')->nullable();
            $table->unsignedInteger('saved');                    // credits (features) or money (top-up)
            $table->timestamps();
        });

        Schema::create('credit_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits');
            $table->decimal('price', 12, 2);                     // after any discount
            $table->string('currency', 8);
            $table->decimal('rate', 12, 4);                      // money per credit at request time
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 10)->default('pending');    // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('ledger_id')->nullable()->constrained('credit_ledger')->nullOnDelete();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('credit_features')->insert([
            ['key' => 'tournament_publish', 'name' => 'Publish a tournament', 'description' => 'Once per tournament, when it leaves draft. Drafts are free.', 'cost' => 0, 'charge_trigger' => 'unlock_once', 'scope' => 'tournament', 'is_active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'poster', 'name' => 'Generate a poster', 'description' => 'Each generated poster, flyer or card. On-screen previews in the editor are free.', 'cost' => 0, 'charge_trigger' => 'per_output', 'scope' => 'tournament', 'is_active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'auction_access', 'name' => 'Auction', 'description' => 'Once per tournament: create and run auctions.', 'cost' => 0, 'charge_trigger' => 'unlock_once', 'scope' => 'tournament', 'is_active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ledwall', 'name' => 'LED wall & live scoring display', 'description' => 'Once per tournament: the LED wall and its operator console.', 'cost' => 0, 'charge_trigger' => 'unlock_once', 'scope' => 'tournament', 'is_active' => true, 'sort' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach ([
            'money_per_credit' => '10',
            'currency' => 'INR',
            'term_singular' => 'Credit',
            'term_plural' => 'Credits',
            'welcome_credits' => '0',
            'topup_instructions' => 'Pay by UPI or bank transfer, then submit the request with your payment reference. Credits are added once the payment is confirmed.',
        ] as $key => $value) {
            DB::table('credit_settings')->insert(['key' => $key, 'value' => $value, 'effective_from' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_topup_requests');
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('credit_settings');
        Schema::dropIfExists('credit_feature_unlocks');
        Schema::dropIfExists('credit_features');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('credit_wallets');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('billing_mode');
        });
    }
};
