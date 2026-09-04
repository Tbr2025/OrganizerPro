<?php

namespace App\Services\Tournament;

use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Tournament leaderboards built from the scorecards, not from ball-by-ball data.
 *
 * PlayerStatisticService aggregates the `balls` table, which the in-app live scorer writes.
 * Almost nothing writes it — ten rows exist across the whole site — because real scoring arrives
 * as `match_results.scorecard_data`, either imported from CricHeroes or typed in on the summary
 * screen. So `player_statistics` stayed empty and every public Stats page said "No batting
 * statistics available yet" while eight scored matches sat one table away.
 *
 * The aggregates are PlayerStatistic instances that are never saved: the view already knows how
 * to render one, and the model already carries the average / strike-rate / economy accessors. A
 * scorecard name that matches nobody on either roster still gets a row, carrying an unsaved
 * Player that holds only the name — a guest or a late signing belongs on the leaderboard, and
 * dropping them would silently move somebody else to the top.
 */
class ScorecardStatisticsService
{
    /** How a dismissal string credits a fielder. */
    private const CAUGHT_AND_BOWLED = '/^c\s*(?:&|and)\s*b\s+(.+)$/iu';
    private const CAUGHT = '/^c\s+(.+?)\s+b\s+(.+)$/iu';
    private const STUMPED = '/^st\s+(.+?)\s+b\s+(.+)$/iu';
    private const RUN_OUT = '/^run\s*out\s*\((.+?)\)/iu';

    /** Every aggregate for the tournament, keyed by a stable per-player key. */
    private array $aggregates = [];

    /** Roster of the tournament, grouped by actual_team_id: [teamId => [['id','name','norm']]]. */
    private array $rosters = [];

    private array $playerCache = [];

    private array $teamCache = [];

    public function hasScorecards(Tournament $tournament): bool
    {
        return Matches::where('tournament_id', $tournament->id)
            ->whereHas('result', fn ($q) => $q->whereNotNull('scorecard_data'))
            ->exists();
    }

    /**
     * @return array{batting: Collection, bowling: Collection, sixes: Collection, fielding: Collection}
     */
    public function leaderboards(Tournament $tournament): array
    {
        $this->aggregates = [];
        $this->loadRosters($tournament);

        $matches = Matches::with(['result', 'teamA', 'teamB'])
            ->where('tournament_id', $tournament->id)
            ->get()
            ->filter(fn ($m) => filled($m->result?->scorecard_data));

        foreach ($matches as $match) {
            $this->ingestMatch($tournament, $match);
        }

        $stats = collect($this->aggregates)->map(fn ($agg) => $this->toStatistic($tournament, $agg));

        return [
            'batting' => $stats->filter(fn ($s) => $s->runs > 0)
                ->sortByDesc('runs')->take(20)->values(),
            'bowling' => $stats->filter(fn ($s) => $s->wickets > 0)
                ->sortByDesc('wickets')->take(20)->values(),
            'sixes' => $stats->filter(fn ($s) => $s->sixes > 0)
                ->sortByDesc('sixes')->take(10)->values(),
            'fielding' => $stats->filter(fn ($s) => ($s->catches + $s->stumpings + $s->run_outs) > 0)
                ->sortByDesc(fn ($s) => $s->catches + $s->stumpings + $s->run_outs)->take(10)->values(),
        ];
    }

    // -----------------------------------------------------------------------
    // Ingest
    // -----------------------------------------------------------------------

    private function ingestMatch(Tournament $tournament, Matches $match): void
    {
        $scorecard = $match->result->scorecard_data;
        if (is_string($scorecard)) {
            $scorecard = json_decode($scorecard, true);
        }
        // Two shapes live in this column: a bare innings array, and a CricHeroes wrapper.
        $innings = $scorecard['innings'] ?? $scorecard;
        if (! is_array($innings)) {
            return;
        }

        $teamIds = array_values(array_filter([$match->team_a_id, $match->team_b_id]));
        $pool = [];
        foreach ($teamIds as $teamId) {
            $pool = array_merge($pool, $this->rosters[$teamId] ?? []);
        }

        foreach (array_values($innings) as $index => $inning) {
            if (! is_array($inning)) {
                continue;
            }

            $battingTeamId = $this->resolveInningsTeam($match, $inning, $index);
            $bowlingTeamId = $this->otherTeam($match, $battingTeamId);

            foreach (($inning['batting'] ?? []) as $row) {
                $this->ingestBatting($match, $pool, $battingTeamId, $row);
            }

            foreach (($inning['did_not_bat'] ?? []) as $name) {
                // Named in the XI but never at the crease: it is still an appearance.
                $key = $this->keyFor($pool, $battingTeamId, is_array($name) ? ($name['name'] ?? '') : $name);
                if ($key) {
                    $this->aggregates[$key]['matchIds'][$match->id] = true;
                }
            }

            foreach (($inning['bowling'] ?? []) as $row) {
                $this->ingestBowling($match, $pool, $bowlingTeamId, $row);
            }

            // Fielding is credited from the batting side's dismissals to the bowling side.
            foreach (($inning['batting'] ?? []) as $row) {
                $this->ingestFielding($match, $pool, $bowlingTeamId, (string) ($row['how_out'] ?? ''));
            }
        }
    }

    private function ingestBatting(Matches $match, array $pool, ?int $teamId, array $row): void
    {
        $key = $this->keyFor($pool, $teamId, $row['name'] ?? '');
        if (! $key) {
            return;
        }

        $agg = &$this->aggregates[$key];
        $agg['matchIds'][$match->id] = true;

        $runs = (int) ($row['runs'] ?? 0);
        $balls = (int) ($row['balls'] ?? 0);
        $howOut = trim((string) ($row['how_out'] ?? ''));
        $notOut = $howOut === '' || preg_match('/^not\s*out$/i', $howOut) === 1;

        $agg['innings_batted']++;
        $agg['runs'] += $runs;
        $agg['balls_faced'] += $balls;
        $agg['fours'] += (int) ($row['fours'] ?? 0);
        $agg['sixes'] += (int) ($row['sixes'] ?? 0);

        if ($notOut) {
            $agg['not_outs']++;
        } elseif ($runs === 0) {
            $agg['ducks']++;
        }

        // A not-out score outranks an identical dismissed one, which is why the flag is part of
        // the comparison and not a separate lookup afterwards.
        if ($runs > $agg['highest_score'] || ($runs === $agg['highest_score'] && $notOut)) {
            $agg['highest_score'] = $runs;
            $agg['highest_not_out'] = $notOut;
        }

        if ($runs >= 100) {
            $agg['hundreds']++;
        } elseif ($runs >= 50) {
            $agg['fifties']++;
        }
    }

    private function ingestBowling(Matches $match, array $pool, ?int $teamId, array $row): void
    {
        $key = $this->keyFor($pool, $teamId, $row['name'] ?? '');
        if (! $key) {
            return;
        }

        $agg = &$this->aggregates[$key];
        $agg['matchIds'][$match->id] = true;

        $balls = $this->oversToBalls($row['overs'] ?? 0);
        $runs = (int) ($row['runs'] ?? 0);
        $wickets = (int) ($row['wickets'] ?? 0);

        $agg['innings_bowled']++;
        $agg['balls_bowled'] += $balls;
        $agg['runs_conceded'] += $runs;
        $agg['wickets'] += $wickets;
        $agg['maidens'] += (int) ($row['maidens'] ?? 0);
        $agg['wides'] += (int) ($row['wides'] ?? 0);
        $agg['no_balls'] += (int) ($row['no_balls'] ?? 0);

        if ($wickets >= 5) {
            $agg['five_wickets']++;
        } elseif ($wickets >= 4) {
            $agg['four_wickets']++;
        }

        // Best bowling: most wickets, and among equal hauls the one that cost least.
        $best = $agg['best'];
        if ($best === null || $wickets > $best[0] || ($wickets === $best[0] && $runs < $best[1])) {
            $agg['best'] = [$wickets, $runs];
        }
    }

    /**
     * Credit a catch, stumping or run out from one dismissal string.
     *
     * `how_out` is free text written by the scorer ("c Faisal TK b Rameez Nawab"), so anything
     * that does not parse is left alone rather than guessed at — a wrong fielder on a public
     * leaderboard is worse than a missing one.
     */
    private function ingestFielding(Matches $match, array $pool, ?int $teamId, string $howOut): void
    {
        $howOut = trim($howOut);
        if ($howOut === '') {
            return;
        }

        $credit = function (string $name, string $field) use ($match, $pool, $teamId) {
            $key = $this->keyFor($pool, $teamId, $name);
            if (! $key) {
                return;
            }
            $this->aggregates[$key][$field]++;
            $this->aggregates[$key]['matchIds'][$match->id] = true;
        };

        if (preg_match(self::CAUGHT_AND_BOWLED, $howOut, $m)) {
            $credit($m[1], 'catches');
            return;
        }
        if (preg_match(self::CAUGHT, $howOut, $m)) {
            $credit($m[1], 'catches');
            return;
        }
        if (preg_match(self::STUMPED, $howOut, $m)) {
            $credit($m[1], 'stumpings');
            return;
        }
        if (preg_match(self::RUN_OUT, $howOut, $m)) {
            // "run out (Jones/Smith)" — a throw and a break, both get the credit.
            foreach (preg_split('#\s*/\s*#', $m[1]) as $fielder) {
                $credit($fielder, 'run_outs');
            }
        }
    }

    // -----------------------------------------------------------------------
    // Identity
    // -----------------------------------------------------------------------

    private function loadRosters(Tournament $tournament): void
    {
        $this->rosters = [];
        $this->playerCache = [];
        $this->teamCache = [];

        $rows = \DB::table('player_actual_team_tournament as pivot')
            ->join('players', 'players.id', '=', 'pivot.player_id')
            ->where('pivot.tournament_id', $tournament->id)
            ->select('players.id', 'players.name', 'players.image_path', 'pivot.actual_team_id')
            ->get();

        foreach ($rows as $row) {
            $this->rosters[$row->actual_team_id][] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'norm' => $this->normalise($row->name),
            ];
            $this->playerCache[(int) $row->id] = $row;
        }
    }

    /**
     * The aggregate key for a scorecard name, creating the bucket if it is new.
     *
     * Matching is scoped to the two squads in this match, never the whole tournament: inside a
     * thirty-name pool a partial like "Vikesh" resolves to one person safely, where across four
     * hundred it would not.
     */
    private function keyFor(array $pool, ?int $teamId, $rawName): ?string
    {
        $rawName = trim((string) (is_array($rawName) ? ($rawName['name'] ?? '') : $rawName));
        if ($rawName === '') {
            return null;
        }

        $norm = $this->normalise($rawName);
        if ($norm === '') {
            return null;
        }

        $playerId = $this->matchPlayer($pool, $norm);

        // An unresolved name is kept per team, so the same nickname on two sides stays two people.
        $key = $playerId ? 'p:' . $playerId : 'n:' . ($teamId ?? 0) . ':' . $norm;

        if (! isset($this->aggregates[$key])) {
            $this->aggregates[$key] = $this->blankAggregate($playerId, $teamId, $this->cleanDisplayName($rawName));
        } elseif ($this->aggregates[$key]['team_id'] === null && $teamId !== null) {
            $this->aggregates[$key]['team_id'] = $teamId;
        }

        return $key;
    }

    private function matchPlayer(array $pool, string $norm): ?int
    {
        foreach ($pool as $candidate) {
            if ($candidate['norm'] === $norm) {
                return $candidate['id'];
            }
        }

        // Fall back to a token-subset match: every meaningful word of the shorter name has to
        // appear in the longer one, so "Vikesh" finds "Vikesh Kumar" but "Ali Khan" never
        // collapses into "Ali Hassan". The longest such overlap wins; a tie is ambiguous and
        // resolves to nobody rather than to a coin flip.
        $wanted = array_filter(explode(' ', $norm), fn ($t) => mb_strlen($t) > 1);
        if (empty($wanted)) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        $tied = false;

        foreach ($pool as $candidate) {
            $have = array_filter(explode(' ', $candidate['norm']), fn ($t) => mb_strlen($t) > 1);
            if (empty($have)) {
                continue;
            }

            [$short, $long] = count($wanted) <= count($have) ? [$wanted, $have] : [$have, $wanted];
            foreach ($short as $token) {
                if (! in_array($token, $long, true)) {
                    continue 2;
                }
            }

            $score = count($short);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate['id'];
                $tied = false;
            } elseif ($score === $bestScore && $candidate['id'] !== $best) {
                $tied = true;
            }
        }

        return $tied ? null : $best;
    }

    /**
     * Fold a scorecard name down to something comparable.
     *
     * Names arrive with the role baked in ("Tarun Kumar  (c)", "Devnath  (wk)"), in any case,
     * and occasionally with a stray id glued on ("Sachin Chhabra2612").
     */
    private function normalise(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = preg_replace('/\s*\((?:c|wk|vc|sub|c\s*&\s*wk|wk\s*&\s*c)\)\s*/u', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $name);
        $name = preg_replace('/\d+/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /** The name as it should read on the page: role suffix gone, capitalisation left alone. */
    private function cleanDisplayName(string $name): string
    {
        $name = preg_replace('/\s*\((?:c|wk|vc|sub|c\s*&\s*wk|wk\s*&\s*c)\)\s*/iu', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function resolveInningsTeam(Matches $match, array $inning, int $index): ?int
    {
        $name = $this->normalise($inning['team_name'] ?? '');

        if ($name !== '') {
            foreach ([[$match->team_a_id, $match->teamA?->name], [$match->team_b_id, $match->teamB?->name]] as [$id, $teamName]) {
                if ($id && $this->normalise($teamName) === $name) {
                    return (int) $id;
                }
            }
        }

        // No usable team name: fall back to the batting order the result records.
        $aBatsFirst = $match->result?->team_a_batting_first ?? true;
        $first = $aBatsFirst ? $match->team_a_id : $match->team_b_id;
        $second = $aBatsFirst ? $match->team_b_id : $match->team_a_id;

        return (int) ($index === 0 ? $first : $second) ?: null;
    }

    private function otherTeam(Matches $match, ?int $teamId): ?int
    {
        if ($teamId === null) {
            return null;
        }

        return (int) $teamId === (int) $match->team_a_id
            ? ($match->team_b_id ? (int) $match->team_b_id : null)
            : ($match->team_a_id ? (int) $match->team_a_id : null);
    }

    // -----------------------------------------------------------------------
    // Shaping
    // -----------------------------------------------------------------------

    private function blankAggregate(?int $playerId, ?int $teamId, string $displayName): array
    {
        return [
            'player_id' => $playerId,
            'team_id' => $teamId,
            'display_name' => $displayName,
            'matchIds' => [],
            'innings_batted' => 0,
            'runs' => 0,
            'balls_faced' => 0,
            'fours' => 0,
            'sixes' => 0,
            'highest_score' => 0,
            'highest_not_out' => false,
            'fifties' => 0,
            'hundreds' => 0,
            'not_outs' => 0,
            'ducks' => 0,
            'innings_bowled' => 0,
            'balls_bowled' => 0,
            'runs_conceded' => 0,
            'wickets' => 0,
            'maidens' => 0,
            'wides' => 0,
            'no_balls' => 0,
            'four_wickets' => 0,
            'five_wickets' => 0,
            'best' => null,
            'catches' => 0,
            'stumpings' => 0,
            'run_outs' => 0,
        ];
    }

    private function toStatistic(Tournament $tournament, array $agg): PlayerStatistic
    {
        $stat = new PlayerStatistic([
            'tournament_id' => $tournament->id,
            'player_id' => $agg['player_id'],
            'actual_team_id' => $agg['team_id'],
            'matches' => count($agg['matchIds']),
            'innings_batted' => $agg['innings_batted'],
            'runs' => $agg['runs'],
            'balls_faced' => $agg['balls_faced'],
            'fours' => $agg['fours'],
            'sixes' => $agg['sixes'],
            'highest_score' => $agg['highest_score'],
            'highest_not_out' => $agg['highest_not_out'],
            'fifties' => $agg['fifties'],
            'hundreds' => $agg['hundreds'],
            'not_outs' => $agg['not_outs'],
            'ducks' => $agg['ducks'],
            'innings_bowled' => $agg['innings_bowled'],
            // True decimal overs, not the 3.4 = "three and four balls" notation, because the
            // model divides straight through by this to get an economy rate.
            'overs_bowled' => round($agg['balls_bowled'] / 6, 2),
            'runs_conceded' => $agg['runs_conceded'],
            'wickets' => $agg['wickets'],
            'maidens' => $agg['maidens'],
            'best_bowling' => $agg['best'] ? $agg['best'][0] . '/' . $agg['best'][1] : null,
            'four_wickets' => $agg['four_wickets'],
            'five_wickets' => $agg['five_wickets'],
            'wides' => $agg['wides'],
            'no_balls' => $agg['no_balls'],
            'catches' => $agg['catches'],
            'stumpings' => $agg['stumpings'],
            'run_outs' => $agg['run_outs'],
        ]);

        // Relations are set rather than loaded: an unresolved name still needs something for the
        // view to print, and a transient Player carrying only a name does that without a row.
        $stat->setRelation('player', $this->playerFor($agg));
        $stat->setRelation('team', $this->teamFor($agg['team_id']));

        return $stat;
    }

    private function playerFor(array $agg): Player
    {
        if ($agg['player_id'] && isset($this->playerCache[$agg['player_id']])) {
            $row = $this->playerCache[$agg['player_id']];
            $player = new Player();
            $player->id = $row->id;
            $player->name = $row->name;
            $player->image_path = $row->image_path;
            $player->exists = true;

            return $player;
        }

        $player = new Player();
        $player->name = $agg['display_name'];

        return $player;
    }

    private function teamFor(?int $teamId): ?ActualTeam
    {
        if (! $teamId) {
            return null;
        }

        if (! array_key_exists($teamId, $this->teamCache)) {
            $this->teamCache[$teamId] = ActualTeam::find($teamId);
        }

        return $this->teamCache[$teamId];
    }

    /** "3.4" is three overs and four balls, not three and two-fifths of an over. */
    private function oversToBalls($overs): int
    {
        $overs = (float) $overs;
        $whole = (int) floor($overs);
        $balls = (int) round(($overs - $whole) * 10);

        return $whole * 6 + min($balls, 5);
    }
}
