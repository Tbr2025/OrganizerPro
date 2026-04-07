<?php

namespace App\Services\CricHeroes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CricHeroesScraper
{
    /**
     * Fetch and parse scorecard data from a CricHeroes match URL.
     * Uses simple HTTP GET — CricHeroes embeds all data as JSON in __NEXT_DATA__.
     */
    public function fetch(string $url): array
    {
        $this->validateUrl($url);

        // Ensure URL ends with /scorecard
        $url = rtrim($url, '/');
        if (!str_ends_with($url, '/scorecard')) {
            $url .= '/scorecard';
        }

        $html = $this->fetchPage($url);
        $data = $this->extractNextData($html);

        return $this->parseData($data);
    }

    /**
     * Fetch ONLY the detailed scorecard data (batting, bowling, FoW, DNB).
     */
    public function fetchScorecard(string $url): ?array
    {
        $this->validateUrl($url);

        $url = rtrim($url, '/');
        if (!str_ends_with($url, '/scorecard')) {
            $url .= '/scorecard';
        }

        $html = $this->fetchPage($url);
        $pageProps = $this->extractNextData($html);

        return $this->parseScorecardData($pageProps);
    }

    private function validateUrl(string $url): void
    {
        if (!preg_match('#https?://(www\.)?cricheroes\.com/(scorecard|match)/#i', $url)) {
            throw new \InvalidArgumentException('Invalid CricHeroes URL. Expected: https://cricheroes.com/scorecard/...');
        }
    }

    private function fetchPage(string $url): string
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Accept' => 'text/html',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch CricHeroes page (HTTP ' . $response->status() . ').');
        }

        return $response->body();
    }

    private function extractNextData(string $html): array
    {
        if (!preg_match('/__NEXT_DATA__[^>]*>(.*?)<\/script>/s', $html, $match)) {
            throw new \RuntimeException('Could not find match data on the CricHeroes page.');
        }

        $json = json_decode($match[1], true);

        if (!$json || !isset($json['props']['pageProps'])) {
            throw new \RuntimeException('Invalid data structure from CricHeroes.');
        }

        return $json['props']['pageProps'];
    }

    private function parseData(array $pageProps): array
    {
        $summary = $pageProps['summaryData']['data'] ?? null;

        if (!$summary) {
            throw new \RuntimeException('No match summary data found.');
        }

        $teams = [];

        // Team A
        if ($teamA = $summary['team_a'] ?? null) {
            $innings = $teamA['innings'][0] ?? null;
            $teams[] = [
                'name' => $teamA['name'],
                'runs' => $innings ? (int) $innings['total_run'] : 0,
                'wickets' => $innings ? (int) $innings['total_wicket'] : 0,
                'overs' => $innings ? (float) $innings['overs_played'] : 0,
                'extras' => $innings ? (int) $innings['total_extra'] : 0,
            ];
        }

        // Team B
        if ($teamB = $summary['team_b'] ?? null) {
            $innings = $teamB['innings'][0] ?? null;
            $teams[] = [
                'name' => $teamB['name'],
                'runs' => $innings ? (int) $innings['total_run'] : 0,
                'wickets' => $innings ? (int) $innings['total_wicket'] : 0,
                'overs' => $innings ? (float) $innings['overs_played'] : 0,
                'extras' => $innings ? (int) $innings['total_extra'] : 0,
            ];
        }

        // Toss
        $toss = null;
        $tossDetails = $summary['toss_details'] ?? '';
        // Format: "Toss: Evexia All Stars opt to field"
        if (preg_match('/Toss:\s*(.+?)\s+(?:opt|elected|chose)\s+to\s+(bat|bowl|field)/i', $tossDetails, $tm)) {
            $toss = [
                'winner' => trim($tm[1]),
                'decision' => strtolower($tm[2]) === 'field' ? 'bowl' : strtolower($tm[2]),
            ];
        }

        // Result
        $result = null;
        $winBy = $summary['win_by'] ?? '';
        $winningTeam = $summary['winning_team'] ?? '';

        if ($winningTeam && $winBy) {
            // Parse "17 runs" or "5 wickets"
            if (preg_match('/(\d+)\s+(runs?|wickets?)/i', $winBy, $rm)) {
                $result = [
                    'winner' => $winningTeam,
                    'margin' => (int) $rm[1],
                    'type' => str_starts_with(strtolower($rm[2]), 'run') ? 'runs' : 'wickets',
                    'summary' => $winningTeam . ' won by ' . $winBy,
                ];
            }
        } elseif (($summary['match_result'] ?? '') === 'Tied') {
            $result = [
                'winner' => null,
                'margin' => null,
                'type' => 'tie',
                'summary' => 'Match Tied',
            ];
        }

        return [
            'teams' => $teams,
            'toss' => $toss,
            'result' => $result,
            'scorecard' => $this->parseScorecardData($pageProps),
        ];
    }

    /**
     * Parse detailed scorecard data from pageProps['scorecard'].
     */
    private function parseScorecardData(array $pageProps): ?array
    {
        $scorecardInnings = $pageProps['scorecard'] ?? null;

        if (!$scorecardInnings || !is_array($scorecardInnings) || count($scorecardInnings) === 0) {
            return null;
        }

        $innings = [];

        foreach ($scorecardInnings as $inn) {
            $inningData = $inn['inning'] ?? [];

            $batting = collect($inn['batting'] ?? [])->map(fn($b) => [
                'name' => $b['name'] ?? '',
                'runs' => (int) ($b['runs'] ?? 0),
                'balls' => (int) ($b['balls'] ?? 0),
                'fours' => (int) ($b['4s'] ?? 0),
                'sixes' => (int) ($b['6s'] ?? 0),
                'strike_rate' => $b['SR'] ?? '0.00',
                'how_out' => $b['how_to_out'] ?? 'not out',
            ])->toArray();

            $bowling = collect($inn['bowling'] ?? [])->map(function ($bw) {
                $overs = (int) ($bw['overs'] ?? 0);
                $remainingBalls = (int) ($bw['balls'] ?? 0);
                $oversDisplay = $remainingBalls > 0 ? "{$overs}.{$remainingBalls}" : (string) $overs;

                return [
                    'name' => $bw['name'] ?? '',
                    'overs' => $oversDisplay,
                    'maidens' => (int) ($bw['maidens'] ?? 0),
                    'runs' => (int) ($bw['runs'] ?? 0),
                    'wickets' => (int) ($bw['wickets'] ?? 0),
                    'economy' => $bw['economy_rate'] ?? '0.00',
                    'wides' => (int) ($bw['wide'] ?? 0),
                    'no_balls' => (int) ($bw['noball'] ?? 0),
                ];
            })->toArray();

            $fowData = $inn['fall_of_wicket']['data'] ?? [];
            $fallOfWickets = collect($fowData)->map(fn($f) => [
                'runs' => (int) ($f['run'] ?? 0),
                'wicket' => (int) ($f['wicket'] ?? 0),
                'over' => $f['over'] ?? 0,
                'player_name' => $f['dismiss_player_name'] ?? '',
            ])->toArray();

            $didNotBat = collect($inn['to_be_bat'] ?? [])->pluck('name')->toArray();

            $extras = $inn['extras'] ?? [];

            $innings[] = [
                'team_name' => $inn['teamName'] ?? '',
                'total_runs' => (int) ($inningData['total_run'] ?? 0),
                'total_wickets' => (int) ($inningData['total_wicket'] ?? 0),
                'overs_played' => $inningData['overs_played'] ?? '0',
                'total_extras' => (int) ($extras['total'] ?? 0),
                'extras_summary' => $extras['summary'] ?? '',
                'batting' => $batting,
                'bowling' => $bowling,
                'fall_of_wickets' => $fallOfWickets,
                'did_not_bat' => $didNotBat,
            ];
        }

        return $innings;
    }
}
