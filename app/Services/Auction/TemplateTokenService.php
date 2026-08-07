<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;

/**
 * The `{token}` vocabulary an HTML auction template can use.
 *
 * Substitution happens on the client, into the authored template string, because tokens
 * have to work in attribute position (`<img src="{player_image}">`) as well as text
 * position — something you cannot do by wrapping values in spans.
 *
 * This class exists so the token map and its escaping are plain, testable PHP rather
 * than logic buried in a Blade view.
 */
class TemplateTokenService
{
    /**
     * Tokens grouped for the authoring cheat-sheet.
     *
     * @return array<string, array<string, string>>
     */
    public static function catalogue(): array
    {
        return [
            'Player' => [
                'player_name' => 'Full name',
                'player_first_name' => 'Everything before the last word',
                'player_last_name' => 'Last word of the name',
                'player_role' => 'Batsman / Bowler / All-Rounder',
                'player_image' => 'Photo URL — use in src=""',
                'has_player_image' => '"1" when a photo exists, else empty',
                'player_matches' => 'Declared career matches',
                'player_runs' => 'Declared career runs',
                'player_wickets' => 'Declared career wickets',
                'player_lot' => 'Lot number',
            ],
            'Money' => [
                'base_price' => 'Formatted base price',
                'current_bid' => 'Formatted live bid',
                'base_price_raw' => 'Base price as a bare number',
                'current_bid_raw' => 'Live bid as a bare number',
                'amount_unit' => 'Points / Coins / $ …',
            ],
            'Bidding' => [
                'leading_team' => 'Team currently leading',
                'leading_team_short' => 'Its short name',
                'team_logo' => 'Leading team logo URL',
                'has_team_logo' => '"1" when a logo exists, else empty',
                'status' => 'on_auction / sold / unsold / skipped / idle',
                'status_label' => 'CURRENT BID / SOLD / UNSOLD / NO BIDS',
            ],
            'Auction' => [
                'auction_name' => 'Auction name',
                'tournament_name' => 'Tournament name',
                'primary_color' => 'Brand colour',
                'secondary_color' => 'Accent colour',
            ],
            'Pool & progress' => [
                'pool_name' => 'Active pool',
                'pool_done' => 'Players done in this pool',
                'pool_total' => 'Players in this pool',
                'sold_count' => 'Sold so far',
                'unsold_count' => 'Unsold so far',
                'total_count' => 'Players in the auction',
            ],
            'Clock & squad' => [
                'timer_seconds' => 'Seconds left, empty when no timer',
                'final_call_label' => 'Closing call text, when one is showing',
                'squad_min' => 'Minimum squad size',
                'squad_max' => 'Maximum squad size, empty when unset',
            ],
        ];
    }

    /** Every token name, flat. */
    public static function tokenNames(): array
    {
        return array_merge(...array_map('array_keys', array_values(static::catalogue())));
    }

    /**
     * The values that never change while a screen is open, baked in server-side.
     *
     * @return array<string, string>
     */
    public static function staticTokens(Auction $auction): array
    {
        return [
            'auction_name' => (string) $auction->name,
            'tournament_name' => (string) ($auction->tournament->name ?? ''),
            'primary_color' => (string) ($auction->primary_color ?? '#00bcd4'),
            'secondary_color' => (string) ($auction->secondary_color ?? '#22c55e'),
        ];
    }

    /**
     * Escape a value for insertion into HTML, in text OR attribute position.
     *
     * This is the most important line in the feature, and it has nothing to do with
     * trusting the template's author. Player names arrive through *public* tournament
     * registration, so a name like `" onerror="…` is a stored-XSS payload that reaches
     * the screen through `{player_name}` no matter who wrote the template. Quotes must
     * be escaped, not just angle brackets.
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Reject markup that could execute.
     *
     * Belt and braces behind the page's CSP, which is what actually stops scripts in a
     * modern browser. Deliberately *rejects* rather than strips: stripping HTML with a
     * regex is a losing game and teaches authors their script half-works, whereas
     * refusing on a match fails safe — a false positive is an annoyance, a false
     * negative is still caught by the CSP.
     *
     * @return string|null the offending construct, or null when the markup is clean
     */
    public static function findUnsafeMarkup(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $patterns = [
            '/<\s*script\b/i' => '<script>',
            '/<\s*iframe\b/i' => '<iframe>',
            '/<\s*object\b/i' => '<object>',
            '/<\s*embed\b/i' => '<embed>',
            '/<\s*base\b/i' => '<base>',
            '/<\s*form\b/i' => '<form>',
            '/javascript\s*:/i' => 'javascript: URL',
            '/srcdoc\s*=/i' => 'srcdoc attribute',
            '/\son\w+\s*=/i' => 'inline event handler (on…=)',
        ];

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $html)) {
                return $label;
            }
        }

        return null;
    }
}
