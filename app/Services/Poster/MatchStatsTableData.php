<?php

namespace App\Services\Poster;

use App\Models\Matches;

/**
 * Every table a match-summary poster can draw, built once from the scorecard.
 *
 * This used to be copy-pasted into three places — the template preview, the automatic summary
 * poster, and the match-page download — and only two of them had it, so a poster downloaded
 * from the match page rendered its tables empty. It is one class now, and all three call it.
 *
 * Team A is always the side that batted FIRST, matching the rest of the summary placeholders.
 *
 * Data keys produced:
 *   batting_table_a|b        top order of that side's innings
 *   bowling_table_a|b        that side's bowlers (in the other innings)
 *   top_batting              best innings of the match, both sides together
 *   top_bowling              best spells of the match, both sides together
 *   match_summary_table      one row per innings: side, score, overs, run rate
 *   fall_of_wickets_a|b      wicket number, score, over
 */
class MatchStatsTableData
{
    /** Every key this builder can produce — the editor's source list is checked against it. */
    public const KEYS = [
        'batting_table_a', 'batting_table_b',
        'bowling_table_a', 'bowling_table_b',
        'top_batting', 'top_bowling',
        'match_summary_table',
        'fall_of_wickets_a', 'fall_of_wickets_b',
    ];

    /**
     * @param  array{a: ?string, b: ?string}  $teamNames  short names for the team column
     * @return array<string, array>  only the keys the scorecard can actually fill
     */
    public static function build(Matches $match, array $teamNames = []): array
    {
        $scorecard = $match->result?->scorecard_data;
        if (is_string($scorecard)) {
            $scorecard = json_decode($scorecard, true);
        }
        if (! is_array($scorecard)) {
            return [];
        }

        // Two shapes on live: a bare innings array, and a CricHeroes wrapper.
        $innings = $scorecard['innings'] ?? $scorecard;
        if (! is_array($innings) || empty($innings)) {
            return [];
        }

        $innings = array_values($innings);
        $first = $innings[0] ?? [];
        $second = $innings[1] ?? [];

        $nameA = $teamNames['a'] ?? ($first['team_name'] ?? 'Team A');
        $nameB = $teamNames['b'] ?? ($second['team_name'] ?? 'Team B');

        $data = [];

        // --- per-side tables -------------------------------------------------
        // The side that bats in an innings is A/B; the side that BOWLS in it is the other one,
        // which is why bowling_table_b comes out of the first innings.
        foreach ([['a', $first, 'b'], ['b', $second, 'a']] as [$batKey, $inning, $bowlKey]) {
            if (! empty($inning['batting'])) {
                $data['batting_table_' . $batKey] = self::battingRows($inning['batting']);
            }
            if (! empty($inning['bowling'])) {
                $data['bowling_table_' . $bowlKey] = self::bowlingRows($inning['bowling']);
            }
            if (! empty($inning['fall_of_wickets'])) {
                $data['fall_of_wickets_' . $batKey] = self::fowRows($inning['fall_of_wickets']);
            }
        }

        // --- the match's best, both sides together ---------------------------
        $allBatting = [];
        $allBowling = [];
        foreach ([[$first, $nameA, $nameB], [$second, $nameB, $nameA]] as [$inning, $batting, $bowling]) {
            foreach (($inning['batting'] ?? []) as $row) {
                $row['team'] = $batting;
                $allBatting[] = $row;
            }
            foreach (($inning['bowling'] ?? []) as $row) {
                $row['team'] = $bowling;
                $allBowling[] = $row;
            }
        }

        if ($allBatting) {
            $data['top_batting'] = self::battingRows($allBatting, 5, true);
        }
        if ($allBowling) {
            $data['top_bowling'] = self::bowlingRows($allBowling, 5, true);
        }

        // --- innings summary --------------------------------------------------
        $summary = [];
        foreach ([[$first, $nameA], [$second, $nameB]] as [$inning, $teamName]) {
            if (empty($inning)) {
                continue;
            }
            $runs = (int) ($inning['total_runs'] ?? 0);
            $overs = (string) ($inning['overs_played'] ?? '0');
            $balls = self::oversToBalls($overs);

            $summary[] = [
                'team' => $teamName,
                'score' => $runs . '/' . (int) ($inning['total_wickets'] ?? 0),
                'runs' => $runs,
                'wickets' => (int) ($inning['total_wickets'] ?? 0),
                'overs' => $overs,
                'extras' => (int) ($inning['total_extras'] ?? 0),
                'run_rate' => $balls > 0 ? number_format($runs / ($balls / 6), 2) : '0.00',
            ];
        }
        if ($summary) {
            $data['match_summary_table'] = $summary;
        }

        return $data;
    }

    /**
     * Best innings first: most runs, and a faster hand breaks a tie.
     *
     * @param  bool  $withTeam  keep the team column, for a combined both-sides table
     */
    private static function battingRows(array $rows, int $take = 3, bool $withTeam = false): array
    {
        usort($rows, function ($x, $y) {
            return ((int) ($y['runs'] ?? 0) <=> (int) ($x['runs'] ?? 0))
                ?: ((int) ($x['balls'] ?? 0) <=> (int) ($y['balls'] ?? 0));
        });

        return array_map(function ($b) use ($withTeam) {
            $runs = (int) ($b['runs'] ?? 0);
            $balls = (int) ($b['balls'] ?? 0);

            return array_filter([
                'name' => self::cleanName($b['name'] ?? ''),
                'team' => $withTeam ? ($b['team'] ?? '') : null,
                'runs' => $runs,
                'balls' => $balls,
                'fours' => (int) ($b['fours'] ?? 0),
                'sixes' => (int) ($b['sixes'] ?? 0),
                // The card carries a strike rate, but not always — compute when it does not.
                'strike_rate' => $b['strike_rate']
                    ?? ($balls > 0 ? number_format($runs / $balls * 100, 2) : '0.00'),
                'how_out' => $b['how_out'] ?? '',
            ], fn ($v) => $v !== null);
        }, array_slice($rows, 0, $take));
    }

    /** Best spell first: most wickets, and the cheaper spell breaks a tie. */
    private static function bowlingRows(array $rows, int $take = 3, bool $withTeam = false): array
    {
        usort($rows, function ($x, $y) {
            return ((int) ($y['wickets'] ?? 0) <=> (int) ($x['wickets'] ?? 0))
                ?: ((int) ($x['runs'] ?? 0) <=> (int) ($y['runs'] ?? 0));
        });

        return array_map(function ($b) use ($withTeam) {
            $runs = (int) ($b['runs'] ?? 0);
            $balls = self::oversToBalls($b['overs'] ?? '0');

            return array_filter([
                'name' => self::cleanName($b['name'] ?? ''),
                'team' => $withTeam ? ($b['team'] ?? '') : null,
                'overs' => (string) ($b['overs'] ?? '0'),
                'maidens' => (int) ($b['maidens'] ?? 0),
                'runs' => $runs,
                'wickets' => (int) ($b['wickets'] ?? 0),
                'economy' => $b['economy']
                    ?? ($balls > 0 ? number_format($runs / ($balls / 6), 2) : '0.00'),
                'figures' => ($b['wickets'] ?? 0) . '/' . $runs,
            ], fn ($v) => $v !== null);
        }, array_slice($rows, 0, $take));
    }

    private static function fowRows(array $rows, int $take = 10): array
    {
        return array_map(fn ($w) => [
            'wicket' => (int) ($w['wicket'] ?? 0),
            'name' => self::cleanName($w['player_name'] ?? ''),
            'score' => ((int) ($w['runs'] ?? 0)) . '/' . ((int) ($w['wicket'] ?? 0)),
            'runs' => (int) ($w['runs'] ?? 0),
            'over' => (string) ($w['over'] ?? ''),
        ], array_slice(array_values($rows), 0, $take));
    }

    /** Scorecard names carry the role inline: "Tarun Kumar  (c)". */
    private static function cleanName(string $name): string
    {
        $name = preg_replace('/\s*\((?:c|wk|vc|sub|c\s*&\s*wk|wk\s*&\s*c)\)\s*/iu', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /** "3.4" is three overs and four balls. */
    private static function oversToBalls($overs): int
    {
        $overs = (float) $overs;
        $whole = (int) floor($overs);
        $balls = (int) round(($overs - $whole) * 10);

        return $whole * 6 + min($balls, 5);
    }
}
