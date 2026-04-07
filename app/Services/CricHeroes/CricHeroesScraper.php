<?php

namespace App\Services\CricHeroes;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Log;

class CricHeroesScraper
{
    /**
     * Fetch and parse scorecard data from a CricHeroes match URL.
     */
    public function fetch(string $url): array
    {
        $this->validateUrl($url);

        $html = $this->fetchPageHtml($url);

        return $this->parseHtml($html);
    }

    private function validateUrl(string $url): void
    {
        if (!preg_match('#https?://(www\.)?cricheroes\.com/#i', $url)) {
            throw new \InvalidArgumentException('Invalid CricHeroes URL.');
        }
    }

    private function fetchPageHtml(string $url): string
    {
        try {
            return Browsershot::url($url)
                ->setNodeBinary(config('browsershot.node_binary', '/usr/bin/node'))
                ->setNpmBinary(config('browsershot.npm_binary', '/usr/bin/npm'))
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
                ->waitUntilNetworkIdle()
                ->timeout(30)
                ->bodyHtml();
        } catch (\Exception $e) {
            Log::error('CricHeroes Browsershot error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to load CricHeroes page. The page may be unavailable or loading too slowly.');
        }
    }

    private function parseHtml(string $html): array
    {
        $teams = [];
        $toss = null;
        $result = null;

        // Parse team scores - patterns like "Team Name 150/6 (20.0 Ov)" or within elements
        // CricHeroes renders scores in various formats, we try multiple patterns:

        // Pattern 1: "TeamName\n150/6\n(20.0 Ov)" spread across elements
        // Pattern 2: Inline "TeamName 150/6 (20.0)"
        // Pattern 3: Structured data in script tags or JSON-LD

        // Try to extract from visible text content
        $text = strip_tags(
            preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html)
        );
        $text = preg_replace('/\s+/', ' ', $text);

        // Look for score patterns: "Team Name 150/6 (20.0 Ov)"
        if (preg_match_all('/([A-Za-z][A-Za-z0-9\s&\-\'\.]+?)\s+(\d{1,3}(?:\d)?)\/(\d{1,2})\s*\(([\d.]+)\s*(?:Ov(?:ers?)?|ov)?\)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $teams[] = [
                    'name' => trim($m[1]),
                    'runs' => (int) $m[2],
                    'wickets' => (int) $m[3],
                    'overs' => (float) $m[4],
                    'extras' => null,
                ];
            }
        }

        // Try extracting extras: "Extras: 12" or "Extras 12" or "E: 12"
        if (preg_match_all('/Extras?\s*:?\s*(\d+)/i', $text, $extrasMatches)) {
            foreach ($extrasMatches[1] as $i => $extras) {
                if (isset($teams[$i])) {
                    $teams[$i]['extras'] = (int) $extras;
                }
            }
        }

        // Parse toss info: "TeamName won the toss and opted to bat/bowl"
        if (preg_match('/([A-Za-z][A-Za-z0-9\s&\-\'\.]+?)\s+won\s+the\s+toss\s+and\s+(?:opted|elected|chose)\s+to\s+(bat|bowl|field)/i', $text, $tossMatch)) {
            $toss = [
                'winner' => trim($tossMatch[1]),
                'decision' => strtolower($tossMatch[2]) === 'field' ? 'bowl' : strtolower($tossMatch[2]),
            ];
        }

        // Parse result: "TeamName won by X runs/wickets"
        if (preg_match('/([A-Za-z][A-Za-z0-9\s&\-\'\.]+?)\s+won\s+by\s+(\d+)\s+(runs?|wickets?)/i', $text, $resultMatch)) {
            $result = [
                'winner' => trim($resultMatch[1]),
                'margin' => (int) $resultMatch[2],
                'type' => str_starts_with(strtolower($resultMatch[3]), 'run') ? 'runs' : 'wickets',
                'summary' => trim($resultMatch[0]),
            ];
        } elseif (preg_match('/match\s+tied/i', $text)) {
            $result = [
                'winner' => null,
                'margin' => null,
                'type' => 'tie',
                'summary' => 'Match Tied',
            ];
        }

        if (empty($teams)) {
            throw new \RuntimeException('Could not parse scorecard data from the page. The page structure may have changed.');
        }

        return [
            'teams' => $teams,
            'toss' => $toss,
            'result' => $result,
        ];
    }
}
