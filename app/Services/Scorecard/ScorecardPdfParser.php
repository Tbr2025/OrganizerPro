<?php

declare(strict_types=1);

namespace App\Services\Scorecard;

/**
 * Parses a CricHeroes "Scorecard" PDF into the SAME canonical array shape that
 * CricHeroesScraper::fetch() returns, so all downstream persistence (MatchResult,
 * scorecard_data, awards) and poster rendering are reused unchanged.
 *
 * Text is extracted with poppler's `pdftotext -layout`, which preserves the
 * column alignment of the scorecard tables (a plain text extractor jams the
 * numeric columns together and is unreliable).
 */
class ScorecardPdfParser
{
    public function parse(string $pdfPath): array
    {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException('Scorecard PDF not found.');
        }

        $text = $this->extractLayoutText($pdfPath);
        $lines = preg_split('/\R/', $text) ?: [];

        $details = $this->parseMatchDetails($text);
        $innings = $this->parseInnings($lines);

        if (count($innings) < 1) {
            throw new \RuntimeException('Could not read any innings from the scorecard PDF. Is this a CricHeroes scorecard export?');
        }

        $teams = $this->buildTeams($innings, $details['totals']);
        $heroes = $this->buildHeroes($innings);

        return [
            'teams'     => $teams,
            'toss'      => $details['toss'],
            'result'    => $details['result'],
            'scorecard' => $innings,
            'heroes'    => $heroes,
        ];
    }

    /**
     * Run `pdftotext -layout <pdf> -` and return its stdout.
     */
    protected function extractLayoutText(string $pdfPath): string
    {
        $bin = $this->findPdfToText();
        $cmd = sprintf('%s -layout -enc UTF-8 %s - 2>/dev/null', $bin, escapeshellarg($pdfPath));
        $text = (string) shell_exec($cmd);

        if (trim($text) === '') {
            throw new \RuntimeException('Could not read text from the PDF. Ensure poppler (pdftotext) is installed on the server.');
        }

        return $text;
    }

    protected function findPdfToText(): string
    {
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        $which = trim((string) @shell_exec('which pdftotext 2>/dev/null'));

        return $which !== '' ? $which : 'pdftotext';
    }

    /**
     * Parse the page-1 "Match Details" / "Match Result" block: team totals, toss,
     * result.
     */
    protected function parseMatchDetails(string $text): array
    {
        $totals = [];
        // "Total  Team Name 187/10 (20.0 Ov)" and the following "Team Name 130/10 (17.3 Ov)" line.
        if (preg_match_all('/([A-Za-z][\w .&\'\/-]+?)\s+(\d+)\/(\d+)\s+\(([\d.]+)\s*Ov\)/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $name = $this->cleanName($row[1]);
                // Skip the innings-header repeats (those also match) by keeping the first two distinct teams.
                if ($name === '' || isset($totals[$name])) {
                    continue;
                }
                $totals[$name] = [
                    'name'    => $name,
                    'runs'    => (int) $row[2],
                    'wickets' => (int) $row[3],
                    'overs'   => (float) $row[4],
                ];
                if (count($totals) >= 2) {
                    break;
                }
            }
        }
        $totals = array_values($totals);

        // Toss: "Toss  Team Name opt to bat"
        $toss = ['winner' => null, 'decision' => null];
        if (preg_match('/Toss\s+(.+?)\s+opt(?:ed)?\s+to\s+(bat|bowl|field)/i', $text, $tm)) {
            $toss['winner'] = $this->cleanName($tm[1]);
            $toss['decision'] = strtolower($tm[2]) === 'bat' ? 'bat' : 'bowl';
        }

        // Result: search directly for the "<Team> won by <n> runs|wickets" line
        // (anchoring on "Result" is unreliable — "Match Result" is a header).
        $result = ['winner' => null, 'margin' => null, 'type' => null, 'summary' => null];
        if (preg_match('/([A-Z][^\n]*?)\s+won\s+by\s+(\d+)\s+(run|wicket)/i', $text, $wm)) {
            $result['winner'] = $this->cleanName(preg_replace('/^.*\bResult\b\s*/i', '', $wm[1]));
            $result['margin'] = (int) $wm[2];
            $result['type'] = stripos($wm[3], 'wicket') === 0 ? 'wickets' : 'runs';
            $result['summary'] = $result['winner'] . ' won by ' . $result['margin'] . ' ' . ($result['type'] === 'wickets' ? 'wickets' : 'runs');
        } elseif (preg_match('/Result\s+([^\n]*?(?:tie|tied|no result|abandon|draw)[^\n]*)/i', $text, $rm)) {
            $result['summary'] = trim(preg_replace('/\s+/', ' ', $rm[1]));
            $result['type'] = preg_match('/tie|tied/i', $rm[1]) ? 'tie' : null;
        }

        return ['totals' => $totals, 'toss' => $toss, 'result' => $result];
    }

    /**
     * Parse every innings block (header, batting, extras, total, bowling, FoW, DNB).
     *
     * @param  string[]  $lines
     */
    protected function parseInnings(array $lines): array
    {
        $innings = [];
        $current = null;
        $section = null; // 'batting' | 'bowling'

        foreach ($lines as $raw) {
            $line = rtrim($raw);
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }

            // Innings header: "Team Name 187/10 (20.0 Ov) (1st Innings)"
            if (preg_match('/^(.+?)\s+(\d+)\/(\d+)\s+\(([\d.]+)\s*Ov\)\s*\((?:1st|2nd|3rd|4th)\s+Innings\)/i', $trim, $h)) {
                if ($current) {
                    $innings[] = $this->finalizeInnings($current);
                }
                $current = [
                    'team_name'      => $this->cleanName($h[1]),
                    'total_runs'     => (int) $h[2],
                    'total_wickets'  => (int) $h[3],
                    'overs_played'   => $h[4],
                    'total_extras'   => 0,
                    'extras_summary' => '',
                    'batting'        => [],
                    'bowling'        => [],
                    'fall_of_wickets' => [],
                    'did_not_bat'    => [],
                    '_fow'           => '',
                ];
                $section = null;
                continue;
            }

            if (!$current) {
                continue;
            }

            if (preg_match('/^No\s+Batsman\b/i', $trim)) { $section = 'batting'; continue; }
            if (preg_match('/^No\s+Bowler\b/i', $trim))  { $section = 'bowling'; continue; }

            if (preg_match('/^Extras:\s*\((.*?)\)\s+(\d+)/i', $trim, $e)) {
                $current['extras_summary'] = trim($e[1]);
                $current['total_extras'] = (int) $e[2];
                continue;
            }
            if (preg_match('/^Total:\s*Overs\s+([\d.]+),\s*Wickets\s+(\d+)\s+(\d+)/i', $trim, $t)) {
                $current['overs_played']  = $t[1];
                $current['total_wickets'] = (int) $t[2];
                $current['total_runs']    = (int) $t[3];
                $section = null;
                continue;
            }
            if (preg_match('/^To Bat:\s*(.+)$/i', $trim, $tb)) {
                $current['did_not_bat'] = array_values(array_filter(array_map('trim', explode(',', $tb[1]))));
                continue;
            }
            if (preg_match('/^Fall of Wickets/i', $trim)) { $section = 'fow'; continue; }

            if ($section === 'batting' && ($row = $this->parseBattingRow($trim)) !== null) {
                $current['batting'][] = $row;
                continue;
            }
            if ($section === 'bowling' && ($row = $this->parseBowlingRow($trim)) !== null) {
                $current['bowling'][] = $row;
                continue;
            }
            if ($section === 'fow') {
                // Buffer FoW text — a single entry can wrap across two lines.
                $current['_fow'] .= ' ' . $trim;
                continue;
            }
        }

        if ($current) {
            $innings[] = $this->finalizeInnings($current);
        }

        return $innings;
    }

    /**
     * Parse the buffered Fall-of-Wickets text and drop the scratch key.
     */
    protected function finalizeInnings(array $inn): array
    {
        $inn['fall_of_wickets'] = $this->parseFallOfWickets($inn['_fow'] ?? '');
        unset($inn['_fow']);

        return $inn;
    }

    /**
     * Batting row: "<no> <Name> (RHB) <status> <R> <B> <M> <4s> <6s> <SR>".
     * The last six whitespace-separated columns are the numeric stats.
     */
    protected function parseBattingRow(string $line): ?array
    {
        if (!preg_match('/^\d+\s+(.+?)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+(?:\.\d+)?)\s*$/', $line, $m)) {
            return null;
        }
        [$nameStatus, $name, $howOut] = $this->splitNameAndStatus($m[1]);

        return [
            'name'        => $name,
            'runs'        => (int) $m[2],
            'balls'       => (int) $m[3],
            'minutes'     => (int) $m[4],
            'fours'       => (int) $m[5],
            'sixes'       => (int) $m[6],
            'strike_rate' => $m[7],
            'how_out'     => $howOut,
            'image_url'   => null,
        ];
    }

    /**
     * Bowling row: "<no> <Name> <O> <M> <R> <W> <0s> <4s> <6s> <WD> <NB> <Eco>".
     * The last eleven columns are numeric.
     */
    protected function parseBowlingRow(string $line): ?array
    {
        if (!preg_match('/^\d+\s+(.+?)\s+([\d.]+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+(?:\.\d+)?)\s*$/', $line, $m)) {
            return null;
        }

        return [
            'name'      => $this->cleanName($m[1]),
            'overs'     => $m[2],
            'maidens'   => (int) $m[3],
            'runs'      => (int) $m[4],
            'wickets'   => (int) $m[5],
            'dots'      => (int) $m[6],
            'fours'     => (int) $m[7],
            'sixes'     => (int) $m[8],
            'wides'     => (int) $m[9],
            'no_balls'  => (int) $m[10],
            'economy'   => $m[11],
            'image_url' => null,
        ];
    }

    /**
     * Fall of wickets: "15-1 (Saeed Nawaz, 1.3 ov), 21-2 (Malik Mujtaba, 3 ov), ..."
     */
    protected function parseFallOfWickets(string $line): array
    {
        $out = [];
        if (preg_match_all('/(\d+)-(\d+)\s*\(([^,]+?),\s*([\d.]+)\s*ov\)/i', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $out[] = [
                    'runs'        => (int) $row[1],
                    'wicket'      => (int) $row[2],
                    'over'        => $row[4],
                    'player_name' => trim($row[3]),
                ];
            }
        }

        return $out;
    }

    /**
     * Split "Faisal Nasir (c) (RHB) b Rifas" into [full, name, howOut].
     * Name = text up to the handedness tag (keeping a "(c)" captain marker out);
     * how-out = the dismissal text after the handedness tag.
     */
    protected function splitNameAndStatus(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (preg_match('/^(.*?)\s*\((?:RHB|LHB|RHO|LHO|RH|LH)\)\s*(.*)$/i', $text, $m)) {
            $name = $m[1];
            $howOut = trim($m[2]);
        } else {
            $name = $text;
            $howOut = '';
        }
        $name = trim(preg_replace('/\s*\(c\)\s*$/i', '', trim($name)));

        return [$text, $this->cleanName($name), $howOut === '' ? 'did not bat' : $howOut];
    }

    protected function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        // Strip a leading list index that occasionally sticks to the name.
        $name = preg_replace('/^\d+\s+/', '', $name);

        return trim($name);
    }

    /**
     * Build the two-team summary array (name/runs/wickets/overs/extras) from the
     * innings, preferring page-1 totals for names/scores and innings for extras.
     */
    protected function buildTeams(array $innings, array $totals): array
    {
        $teams = [];
        foreach ($innings as $i => $inn) {
            $teams[] = [
                'name'    => $inn['team_name'],
                'runs'    => $inn['total_runs'],
                'wickets' => $inn['total_wickets'],
                'overs'   => (float) $inn['overs_played'],
                'extras'  => $inn['total_extras'],
            ];
        }
        // Fallback to page-1 totals if an innings header was missing.
        if (count($teams) < 2 && count($totals) >= 2) {
            return array_map(fn ($t) => $t + ['extras' => 0], $totals);
        }

        return $teams;
    }

    /**
     * Best batter = highest run-scorer; best bowler = most wickets (then fewest
     * runs conceded). POTM is left null — the PDF does not mark it.
     */
    protected function buildHeroes(array $innings): array
    {
        $bestBat = null;
        $bestBowl = null;
        foreach ($innings as $inn) {
            foreach ($inn['batting'] as $b) {
                if ($bestBat === null || $b['runs'] > $bestBat['runs']) {
                    $bestBat = $b + ['team' => $inn['team_name']];
                }
            }
            foreach ($inn['bowling'] as $b) {
                $better = $bestBowl === null
                    || $b['wickets'] > $bestBowl['wickets']
                    || ($b['wickets'] === $bestBowl['wickets'] && $b['runs'] < $bestBowl['runs']);
                if ($better) {
                    $bestBowl = $b + ['team' => $inn['team_name']];
                }
            }
        }

        return [
            'player_of_the_match' => null,
            'best_batter' => $bestBat ? [
                'name'      => $bestBat['name'],
                'team'      => $bestBat['team'],
                'runs'      => $bestBat['runs'],
                'balls'     => $bestBat['balls'],
                'fours'     => $bestBat['fours'],
                'sixes'     => $bestBat['sixes'],
                'image_url' => null,
            ] : null,
            'best_bowler' => $bestBowl ? [
                'name'      => $bestBowl['name'],
                'team'      => $bestBowl['team'],
                'overs'     => $bestBowl['overs'],
                'wickets'   => $bestBowl['wickets'],
                'runs'      => $bestBowl['runs'],
                'economy'   => $bestBowl['economy'],
                'image_url' => null,
            ] : null,
        ];
    }
}
