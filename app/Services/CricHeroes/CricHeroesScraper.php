<?php

namespace App\Services\CricHeroes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CricHeroesScraper
{
    /**
     * Fetch and parse scorecard data from a CricHeroes match URL.
     * Uses simple HTTP GET — CricHeroes embeds all data as JSON in __NEXT_DATA__.
     */
    public function fetch(string $url): array
    {
        $this->validateUrl($url);

        $url = $this->normalizeScorecardUrl($url);

        $html = $this->fetchPage($url);
        $data = $this->extractNextData($html);

        $result = $this->parseData($data);

        // Extract heroes from summaryData (same page, no extra request needed)
        $result['heroes'] = $this->extractHeroesFromSummary($data);

        return $result;
    }

    /**
     * Extract heroes/awards data from summaryData.data on the scorecard page.
     * Uses: player_of_the_match, best_performances.batting[0], best_performances.bowling[0]
     */
    private function extractHeroesFromSummary(array $pageProps): ?array
    {
        $summary = $pageProps['summaryData']['data'] ?? null;
        if (!$summary) return null;

        $heroes = [];

        // Log raw heroes fields for debugging CricHeroes schema
        if (!empty($summary['player_of_the_match'])) {
            Log::info('CricHeroes raw POTM fields', array_keys($summary['player_of_the_match']));
        }

        // Player of the Match
        $potm = $summary['player_of_the_match'] ?? null;
        if ($potm && !empty($potm['player_name'])) {
            $heroes['player_of_the_match'] = [
                'name' => $potm['player_name'],
                'team' => $potm['team_name'] ?? '',
                'image_url' => $potm['profile_photo_url'] ?? $potm['photo'] ?? $potm['player_photo'] ?? null,
            ];
        }

        // Best Batter — first entry in best_performances.batting
        $bestBatting = $summary['best_performances']['batting'] ?? [];
        if (!empty($bestBatting[0])) {
            $bat = $bestBatting[0];
            $heroes['best_batter'] = [
                'name' => $bat['player_name'] ?? '',
                'team' => $bat['team_name'] ?? '',
                'runs' => (int) ($bat['runs'] ?? 0),
                'balls' => (int) ($bat['balls'] ?? 0),
                'fours' => (int) ($bat['4s'] ?? 0),
                'sixes' => (int) ($bat['6s'] ?? 0),
                'image_url' => $bat['profile_photo_url'] ?? $bat['photo'] ?? $bat['player_photo'] ?? null,
            ];
        }

        // Best Bowler — first entry in best_performances.bowling
        $bestBowling = $summary['best_performances']['bowling'] ?? [];
        if (!empty($bestBowling[0])) {
            $bowl = $bestBowling[0];
            $heroes['best_bowler'] = [
                'name' => $bowl['player_name'] ?? '',
                'team' => $bowl['team_name'] ?? '',
                'overs' => (string) ($bowl['overs'] ?? '0'),
                'wickets' => (int) ($bowl['wickets'] ?? 0),
                'runs' => (int) ($bowl['runs'] ?? 0),
                'economy' => (string) ($bowl['economy_rate'] ?? '0.00'),
                'image_url' => $bowl['profile_photo_url'] ?? $bowl['photo'] ?? $bowl['player_photo'] ?? null,
            ];
        }

        return !empty($heroes) ? $heroes : null;
    }

    /**
     * Fetch ONLY the detailed scorecard data (batting, bowling, FoW, DNB).
     */
    public function fetchScorecard(string $url): ?array
    {
        $this->validateUrl($url);

        $url = $this->normalizeScorecardUrl($url);

        $html = $this->fetchPage($url);
        $pageProps = $this->extractNextData($html);

        return $this->parseScorecardData($pageProps);
    }

    /**
     * Download a player image from CricHeroes and store it locally.
     */
    public static function downloadPlayerImage(string $imageUrl, string $playerName): ?string
    {
        try {
            $response = Http::timeout(10)->get($imageUrl);
            if (!$response->successful()) return null;

            $extension = 'jpg';
            $filename = 'player_images/' . Str::slug($playerName) . '-' . time() . '.' . $extension;
            Storage::disk('public')->put($filename, $response->body());

            return $filename;
        } catch (\Exception $e) {
            Log::warning('Failed to download CricHeroes image: ' . $e->getMessage());
            return null;
        }
    }

    private function validateUrl(string $url): void
    {
        if (!preg_match('#https?://(www\.)?cricheroes\.com/(scorecard|match)/#i', $url)) {
            throw new \InvalidArgumentException('Invalid CricHeroes URL. Expected: https://cricheroes.com/scorecard/...');
        }
    }

    /**
     * Normalise a CricHeroes match URL to its `/scorecard` tab.
     *
     * Match URLs end with a tab segment (summary, scorecard, commentary, …).
     * Blindly appending `/scorecard` to e.g. a `/summary` URL produces an
     * invalid path that 404s, so we strip any trailing tab first, then ensure
     * the URL ends with the `/scorecard` tab.
     */
    private function normalizeScorecardUrl(string $url): string
    {
        $url = rtrim($url, '/');

        $tabs = ['summary', 'scorecard', 'commentary', 'insights', 'live', 'team-comparison', 'overs', 'mvp', 'analysis'];
        $segments = explode('/', $url);
        if (in_array(strtolower((string) end($segments)), $tabs, true)) {
            array_pop($segments);
            $url = implode('/', $segments);
        }

        if (!str_ends_with($url, '/scorecard')) {
            $url .= '/scorecard';
        }

        return $url;
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
            // CricHeroes migrated to the Next.js App Router: the page no longer
            // embeds match data in __NEXT_DATA__ and instead loads it client-side
            // via their API. Server-side HTML scraping can no longer read it.
            throw new \RuntimeException('CricHeroes no longer embeds match data in the page (site updated). Use "Or paste scorecard text manually" instead, or import via the CricHeroes API.');
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

            // Log raw field keys from first batting entry for debugging CricHeroes schema changes
            if (empty($innings) && !empty($inn['batting'][0])) {
                Log::info('CricHeroes raw batting fields', array_keys($inn['batting'][0]));
            }
            if (empty($innings) && !empty($inn['bowling'][0])) {
                Log::info('CricHeroes raw bowling fields', array_keys($inn['bowling'][0]));
            }

            $batting = collect($inn['batting'] ?? [])->map(fn($b) => [
                'name' => $b['name'] ?? '',
                'runs' => (int) ($b['runs'] ?? 0),
                'balls' => (int) ($b['balls'] ?? 0),
                'fours' => (int) ($b['4s'] ?? 0),
                'sixes' => (int) ($b['6s'] ?? 0),
                'strike_rate' => $b['SR'] ?? '0.00',
                'how_out' => $b['how_to_out'] ?? 'not out',
                'image_url' => $b['profile_photo_url'] ?? $b['photo'] ?? $b['player_photo'] ?? null,
            ])->toArray();

            $bowling = collect($inn['bowling'] ?? [])->map(function ($bw) {
                // CricHeroes returns overs already in cricket notation (e.g. "0.4"
                // = 0 overs, 4 balls). Casting to int dropped the balls, so use the
                // raw value directly and only fall back to a balls count if needed.
                $oversRaw = $bw['overs'] ?? null;
                if ($oversRaw !== null && $oversRaw !== '') {
                    $oversDisplay = (string) $oversRaw;
                } else {
                    $balls = (int) ($bw['balls'] ?? 0);
                    $oversDisplay = intdiv($balls, 6) . '.' . ($balls % 6);
                }

                return [
                    'name' => $bw['name'] ?? '',
                    'overs' => $oversDisplay,
                    'maidens' => (int) ($bw['maidens'] ?? 0),
                    'runs' => (int) ($bw['runs'] ?? 0),
                    'wickets' => (int) ($bw['wickets'] ?? 0),
                    'economy' => $bw['economy_rate'] ?? '0.00',
                    'wides' => (int) ($bw['wide'] ?? 0),
                    'no_balls' => (int) ($bw['noball'] ?? 0),
                    'image_url' => $bw['profile_photo_url'] ?? $bw['photo'] ?? $bw['player_photo'] ?? null,
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
