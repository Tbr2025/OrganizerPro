<?php

declare(strict_types=1);

namespace App\Services\LedWall;

use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use App\Services\Scoring\MatchStateService;
use App\Services\Tournament\ScorecardStatisticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * What a public screen may know about a match: the scoreboard and the panels'
 * data, built from MatchStateService::snapshot().
 *
 * Names, scores and figures only — never a phone number, an email or an id a
 * screen could use against an admin endpoint. Cached per score_version, so a
 * hundred screens polling cost one snapshot per ball.
 */
class WallState
{
    public function __construct(private readonly MatchStateService $state) {}

    public function forMatch(Matches $match): array
    {
        $version = (int) Matches::whereKey($match->id)->value('score_version');

        return Cache::remember("wall-state:{$match->id}:{$version}", now()->addMinutes(10), function () use ($match) {
            $match = Matches::with(['tournament.settings', 'teamA.players.player', 'teamB.players.player', 'lineups', 'tossWinner'])
                ->findOrFail($match->id);

            return $this->build($match, $this->state->snapshot($match));
        });
    }

    public function build(Matches $match, array $s): array
    {
        $team = fn (?ActualTeam $t) => $t ? [
            'id' => $t->id,
            'name' => $t->name,
            'short' => $t->short_name ?: mb_strtoupper(mb_substr($t->name, 0, 3)),
            'logo' => $t->team_logo ? Storage::url($t->team_logo) : null,
        ] : null;

        $teams = [(int) $match->team_a_id => $team($match->teamA), (int) $match->team_b_id => $team($match->teamB)];
        $live = $s['innings'][$s['current_innings']];

        $innings = [];
        foreach ([1, 2] as $n) {
            $i = $s['innings'][$n];
            $innings[$n] = [
                'team' => $teams[$i['batting_team_id']] ?? null,
                'runs' => $i['runs'],
                'wickets' => $i['wickets'],
                'overs' => $i['overs'],
                'run_rate' => $i['run_rate'],
                'extras' => $i['extras'],
                'batted' => $i['balls_bowled'] > 0,
                'complete' => $i['complete'],
                'batters' => array_map(fn ($b) => $this->batter($b), $i['batters']),
                'bowlers' => array_map(fn ($b) => $this->bowler($b), $i['bowlers']),
                'fall_of_wickets' => array_map(fn ($w) => array_diff_key($w, ['batter_id' => 1]), $i['fall_of_wickets']),
                'overs_summary' => array_map(fn ($o) => array_diff_key($o, ['bowler_id' => 1]), $i['overs_summary']),
            ];
        }

        $batters = collect($live['batters'])->keyBy('id');
        $bowlers = collect($live['bowlers'])->keyBy('id');
        $atCrease = fn (?int $id, bool $onStrike) => $id && $batters->has($id)
            ? $this->batter($batters[$id]) + ['on_strike' => $onStrike]
            : null;

        $striker = $match->striker_id ? (int) $match->striker_id : $live['striker_id'];
        $nonStriker = $match->non_striker_id ? (int) $match->non_striker_id : $live['non_striker_id'];
        $lastOvers = $live['overs_summary'];
        $lastComplete = collect($lastOvers)->reverse()->first(fn ($o) => count(array_filter($o['balls'], fn ($b) => ! preg_match('/wd|nb/', $b))) >= 6);

        $tournament = $match->tournament;

        return [
            'match_id' => (int) $match->id,
            'version' => $s['score_version'],
            'status' => $this->status($match, $s),
            'tournament' => [
                'name' => $tournament?->name,
                'logo' => $tournament?->settings?->logo_url ?? $tournament?->logo_url,
            ],
            'match' => [
                'name' => $match->name,
                'overs_limit' => $s['overs_limit'],
                'venue' => $match->venue,
                'toss' => $match->tossWinner ? "{$match->tossWinner->name} won the toss and chose to {$match->toss_decision}" : null,
            ],
            'teams' => array_values(array_filter($teams)),
            'current_innings' => $s['current_innings'],
            'batting' => $teams[$live['batting_team_id']] ?? null,
            'bowling' => $teams[$live['bowling_team_id']] ?? null,
            'score' => [
                'runs' => $live['runs'],
                'wickets' => $live['wickets'],
                'overs' => $live['overs'],
                'run_rate' => $live['run_rate'],
                'this_over' => $live['over_complete'] ? [] : $live['this_over'],
            ],
            'chase' => $s['chase'],
            'striker' => $live['balls_bowled'] ? $atCrease($striker, true) : null,
            'non_striker' => $live['balls_bowled'] ? $atCrease($nonStriker, false) : null,
            'bowler' => $live['bowler_id'] && $bowlers->has($live['bowler_id']) ? $this->bowler($bowlers[$live['bowler_id']]) : null,
            'partnership' => $live['partnership'],
            'last_wicket' => $live['last_wicket'],
            'last_over' => $lastComplete ? array_diff_key($lastComplete, ['bowler_id' => 1]) + [
                'bowler' => $bowlers->has($lastComplete['bowler_id']) ? $bowlers[$lastComplete['bowler_id']]['name'] : null,
            ] : null,
            'innings' => $innings,
            'top_batters' => array_map(fn ($b) => $this->batter($b), $s['top_batters']),
            'top_bowlers' => array_map(fn ($b) => $this->bowler($b), $s['top_bowlers']),
            'result' => $s['result'] ? $this->result($s['result'], $teams) : null,
        ];
    }

    /**
     * Tournament leaderboards and sponsors: slow-moving, so cached separately
     * and much longer than the per-ball state.
     */
    public function tournamentExtras(Tournament $tournament): array
    {
        return Cache::remember("wall-tournament:{$tournament->id}", now()->addMinutes(5), function () use ($tournament) {
            $batting = $bowling = [];
            try {
                $stats = app(ScorecardStatisticsService::class);
                if ($stats->hasScorecards($tournament)) {
                    $boards = $stats->leaderboards($tournament);
                    $batting = $boards['batting']->take(5)->map(fn ($p) => [
                        'name' => $p->player?->name, 'team' => $p->team?->name,
                        'runs' => (int) $p->runs, 'innings' => (int) $p->innings_batted,
                        'highest' => (int) $p->highest_score, 'strike_rate' => $p->balls_faced ? round($p->runs / $p->balls_faced * 100, 1) : null,
                    ])->values()->all();
                    $bowling = $boards['bowling']->take(5)->map(fn ($p) => [
                        'name' => $p->player?->name, 'team' => $p->team?->name,
                        'wickets' => (int) $p->wickets, 'best' => $p->best_bowling,
                        'economy' => $p->overs_bowled > 0 ? round($p->runs_conceded / $p->overs_bowled, 2) : null,
                    ])->values()->all();
                }
            } catch (Throwable $e) {
                // A leaderboard is never worth a blank wall.
                report($e);
            }

            $sponsors = TournamentBanner::where('tournament_id', $tournament->id)
                ->where('is_active', true)->orderBy('sort_order')->limit(8)->get()
                ->map(fn ($b) => ['image' => Storage::url($b->image_path), 'alt' => $b->alt_text])
                ->filter(fn ($b) => $b['image'])->values()->all();

            return ['tournament_batting' => $batting, 'tournament_bowling' => $bowling, 'sponsors' => $sponsors];
        });
    }

    private function batter(array $b): array
    {
        return [
            'name' => $b['name'], 'runs' => $b['runs'], 'balls' => $b['balls'],
            'fours' => $b['fours'], 'sixes' => $b['sixes'], 'strike_rate' => $b['strike_rate'],
            'out' => $b['out'], 'how_out' => $b['how_out'],
        ];
    }

    private function bowler(array $b): array
    {
        return [
            'name' => $b['name'], 'overs' => $b['overs'], 'maidens' => $b['maidens'],
            'runs' => $b['runs'], 'wickets' => $b['wickets'], 'economy' => $b['economy'],
        ];
    }

    private function result(array $r, array $teams): array
    {
        $winner = $r['winner_team_id'] ? ($teams[$r['winner_team_id']] ?? null) : null;
        $text = match ($r['type']) {
            'manual' => $r['text'],
            'tie' => 'Match tied',
            'wickets' => ($winner['name'] ?? 'Winner') . " won by {$r['margin']} " . ($r['margin'] === 1 ? 'wicket' : 'wickets'),
            default => ($winner['name'] ?? 'Winner') . " won by {$r['margin']} " . ($r['margin'] === 1 ? 'run' : 'runs'),
        };

        return ['winner' => $winner, 'type' => $r['type'], 'margin' => $r['margin'], 'text' => $text];
    }

    private function status(Matches $match, array $s): string
    {
        return match (true) {
            (bool) $match->is_cancelled => 'cancelled',
            $s['match_complete'] => 'completed',
            $s['innings'][1]['balls_bowled'] === 0 => 'prematch',
            $s['current_innings'] === 1 && $s['innings'][1]['complete'] => 'innings_break',
            // Switched to the chase, first ball not yet bowled.
            $s['current_innings'] === 2 && $s['innings'][2]['balls_bowled'] === 0 => 'innings_break',
            default => 'live',
        };
    }
}
