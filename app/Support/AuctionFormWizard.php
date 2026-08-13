<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\MessageBag;

/**
 * Which step of the auction wizard a field lives on, and what the form should be
 * repopulated with after a refused save.
 *
 * The create and edit forms are the same five-step wizard, and both used to come back from a
 * validation failure showing step 1 and the values already in the database — so an operator
 * who typed a rejected number on step 2 saw their input vanish with no indication of where
 * the problem was. The layout now lists the messages; this decides where to send the operator
 * and what to put back in the boxes.
 *
 * Kept out of the Blade files because the two wizards must not drift apart on either answer.
 */
class AuctionFormWizard
{
    /**
     * field name => step number.
     *
     * Derived from where each input actually renders, which is not always where its label
     * suggests: the bid timer and the sealed-round settings sit on Details, not Bid Rules.
     */
    public const FIELD_STEPS = [
        // 1 — Details
        'name' => 1,
        'organization_id' => 1,
        'tournament_id' => 1,
        'status' => 1,
        'bid_type' => 1,
        'open_bid_mode' => 1,
        'bid_timer_seconds' => 1,
        'bid_timer_reset_seconds' => 1,
        'timer_enabled' => 1,
        'timer_expiry_action' => 1,
        'final_call_enabled' => 1,
        'final_call_interval_seconds' => 1,
        'online_bid_limit_from' => 1,
        'online_bid_limit_to' => 1,
        'closed_bid_step' => 1,
        'closed_bid_starts_at' => 1,
        'closed_bid_max_pct_of_budget' => 1,
        'closed_bid_max_rebid_rounds' => 1,
        'closed_bid_timer_seconds' => 1,
        'closed_bid_requires_acceptance' => 1,
        'closed_bid_tie_breaker' => 1,

        // 2 — Financials
        'max_budget_per_team' => 2,
        'base_price' => 2,
        'min_squad_size' => 2,
        // The inherit/override switch sits with the squad rules it governs.
        'overrides_tournament_rules' => 2,
        'max_squad_size' => 2,
        'min_price_per_player' => 2,
        'default_retained_value' => 2,
        'expected_retained_per_team' => 2,
        'amount_unit' => 2,
        'amount_unit_label' => 2,
        'team_budgets' => 2,
        'notifications_enabled' => 2,
        'email_test_mode' => 2,
        'email_dispatch' => 2,

        // 3 — Bid Rules
        'bid_rules' => 3,
        'quick_bid_steps' => 3,

        // 4 — Players
        'pools' => 4,

        // 5 — Branding
        'background_image' => 5,
        'auction_logo' => 5,
        'waiting_background_image' => 5,
        'primary_color' => 5,
        'secondary_color' => 5,
        'auction_template_id' => 5,
        'ticker_template_id' => 5,
    ];

    /**
     * The step to open on: the earliest one with a failure, or 1 when nothing failed.
     *
     * Earliest rather than "the first message", because the operator has to work forwards
     * through the wizard anyway and messages come back in rule order, not step order.
     */
    public static function firstFailingStep(MessageBag $errors): int
    {
        $steps = [];

        foreach ($errors->keys() as $key) {
            // `bid_rules.0.to` and `team_budgets.7` belong to their root field's step.
            $root = strtok($key, '.');

            if (isset(self::FIELD_STEPS[$root])) {
                $steps[] = self::FIELD_STEPS[$root];
            }
        }

        return $steps ? min($steps) : 1;
    }

    /**
     * The auction's saved attributes with whatever was just posted laid over the top.
     *
     * Only known form fields are taken from the old input — `_token`, `_method` and the file
     * uploads have no business in the Alpine state, and `pools` is rebuilt from its own
     * arguments rather than from this array.
     */
    public static function repopulate(array $attributes): array
    {
        $old = array_intersect_key(old(), self::FIELD_STEPS);

        // A file input posts nothing on failure; keeping the saved path means the branding
        // preview does not blank out just because the form bounced.
        unset($old['background_image'], $old['auction_logo'], $old['waiting_background_image'], $old['pools']);

        return array_merge($attributes, $old);
    }
}
