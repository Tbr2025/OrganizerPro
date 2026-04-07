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
        ];
    }
}
