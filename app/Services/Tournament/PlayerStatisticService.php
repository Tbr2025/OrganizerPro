<?php

namespace App\Services\Tournament;

use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerStatisticService
{
    /**
     * Update statistics for all players after a match is completed
     */
    public function updateFromMatch(Matches $match): void
    {
        if ($match->status !== 'completed') {
            return;
        }

        $tournament = $match->tournament;
        $balls = Ball::where('match_id', $match->id)->get();

        if ($balls->isEmpty()) {
            return;
        }

        // Get all players involved in the match
        $batsmanIds = $balls->pluck('batsman_id')->unique()->filter();
        $bowlerIds = $balls->pluck('bowler_id')->unique()->filter();
        $fielderIds = $balls->pluck('fielder_id')->unique()->filter();

        $allTeamUserIds = $batsmanIds->merge($bowlerIds)->merge($fielderIds)->unique();

        // Get ActualTeamUser records with player info
        $teamUsers = ActualTeamUser::with('player')
            ->whereIn('id', $allTeamUserIds)
            ->get()
            ->keyBy('id');

        // Process batting stats
        $this->processBattingStats($balls, $teamUsers, $tournament);

        // Process bowling stats
        $this->processBowlingStats($balls, $teamUsers, $tournament);

        // Process fielding stats
        $this->processFieldingStats($balls, $teamUsers, $tournament);
    }

    /**
     * Process batting statistics from balls
     */
    private function processBattingStats(Collection $balls, Collection $teamUsers, Tournament $tournament): void
    {
        $batsmanStats = [];

        foreach ($balls as $ball) {
            $batsmanId = $ball->batsman_id;
            if (!$batsmanId || !isset($teamUsers[$batsmanId])) {
                continue;
            }

            $teamUser = $teamUsers[$batsmanId];
            $player = $teamUser->player;

            if (!$player) {
                continue;
            }

            $playerId = $player->id;

            if (!isset($batsmanStats[$playerId])) {
                $batsmanStats[$playerId] = [
                    'player' => $player,
                    'team_id' => $teamUser->actual_team_id,
                    'runs' => 0,
                    'balls_faced' => 0,
                    'fours' => 0,
                    'sixes' => 0,
                    'is_out' => false,
                    'highest_score' => 0,
                ];
            }

            // Count legal balls faced (not wides)
            if ($ball->extra_type !== 'wide') {
                $batsmanStats[$playerId]['balls_faced']++;
            }

            // Add runs (excluding extras like byes, leg byes)
            $batsmanStats[$playerId]['runs'] += $ball->runs;

            // Count boundaries
            if ($ball->runs === 4) {
                $batsmanStats[$playerId]['fours']++;
            } elseif ($ball->runs === 6) {
                $batsmanStats[$playerId]['sixes']++;
            }

            // Check if out (for batsman dismissals)
            if ($ball->is_wicket && in_array($ball->dismissal_type, ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket'])) {
                $batsmanStats[$playerId]['is_out'] = true;
            }
        }

        // Update database for each batsman
        foreach ($batsmanStats as $playerId => $stats) {
            $this->updateBattingStatistic($tournament, $stats);
        }
    }

    /**
     * Update batting statistics for a player
     */
    private function updateBattingStatistic(Tournament $tournament, array $stats): void
    {
        $player = $stats['player'];

        $statRecord = PlayerStatistic::firstOrCreate(
            [
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
            ],
            [
                'actual_team_id' => $stats['team_id'],
                'matches' => 0,
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
                'overs_bowled' => 0,
                'runs_conceded' => 0,
                'wickets' => 0,
                'maidens' => 0,
                'best_bowling' => null,
                'four_wickets' => 0,
                'five_wickets' => 0,
                'wides' => 0,
                'no_balls' => 0,
                'catches' => 0,
                'stumpings' => 0,
                'run_outs' => 0,
            ]
        );

        // Only count as innings if faced at least one ball
        if ($stats['balls_faced'] > 0) {
            $statRecord->innings_batted++;
            $statRecord->runs += $stats['runs'];
            $statRecord->balls_faced += $stats['balls_faced'];
            $statRecord->fours += $stats['fours'];
            $statRecord->sixes += $stats['sixes'];

            // Update highest score
            if ($stats['runs'] > $statRecord->highest_score) {
                $statRecord->highest_score = $stats['runs'];
                $statRecord->highest_not_out = !$stats['is_out'];
            }

            // Count 50s and 100s
            if ($stats['runs'] >= 100) {
                $statRecord->hundreds++;
            } elseif ($stats['runs'] >= 50) {
                $statRecord->fifties++;
            }

            // Count ducks
            if ($stats['runs'] === 0 && $stats['is_out']) {
                $statRecord->ducks++;
            }

            // Count not outs
            if (!$stats['is_out']) {
                $statRecord->not_outs++;
            }
        }

        // Increment match count (we'll deduplicate later)
        $statRecord->matches = max($statRecord->matches, $statRecord->innings_batted);

        $statRecord->save();
    }

    /**
     * Process bowling statistics from balls
     */
    private function processBowlingStats(Collection $balls, Collection $teamUsers, Tournament $tournament): void
    {
        $bowlerStats = [];

        foreach ($balls as $ball) {
            $bowlerId = $ball->bowler_id;
            if (!$bowlerId || !isset($teamUsers[$bowlerId])) {
                continue;
            }

            $teamUser = $teamUsers[$bowlerId];
            $player = $teamUser->player;

            if (!$player) {
                continue;
            }

            $playerId = $player->id;

            if (!isset($bowlerStats[$playerId])) {
                $bowlerStats[$playerId] = [
                    'player' => $player,
                    'team_id' => $teamUser->actual_team_id,
                    'runs_conceded' => 0,
                    'balls_bowled' => 0,
                    'wickets' => 0,
                    'wides' => 0,
                    'no_balls' => 0,
                    'overs' => [],
                ];
            }

            // Track overs for maiden calculation
            $overKey = $ball->over;
            if (!isset($bowlerStats[$playerId]['overs'][$overKey])) {
                $bowlerStats[$playerId]['overs'][$overKey] = ['runs' => 0, 'balls' => 0];
            }

            // Count legal balls
            if (!in_array($ball->extra_type, ['wide', 'no_ball'])) {
                $bowlerStats[$playerId]['balls_bowled']++;
                $bowlerStats[$playerId]['overs'][$overKey]['balls']++;
            }

            // Add runs conceded
            $runsConceded = $ball->runs + $ball->extra_runs;
            $bowlerStats[$playerId]['runs_conceded'] += $runsConceded;
            $bowlerStats[$playerId]['overs'][$overKey]['runs'] += $runsConceded;

            // Count extras
            if ($ball->extra_type === 'wide') {
                $bowlerStats[$playerId]['wides']++;
            } elseif ($ball->extra_type === 'no_ball') {
                $bowlerStats[$playerId]['no_balls']++;
            }

            // Count wickets (bowler gets credit for most dismissal types except run outs)
            if ($ball->is_wicket && !in_array($ball->dismissal_type, ['run_out', 'retired', 'retired_hurt', 'obstructing'])) {
                $bowlerStats[$playerId]['wickets']++;
            }
        }

        // Update database for each bowler
        foreach ($bowlerStats as $playerId => $stats) {
            $this->updateBowlingStatistic($tournament, $stats);
        }
    }

    /**
     * Update bowling statistics for a player
     */
    private function updateBowlingStatistic(Tournament $tournament, array $stats): void
    {
        $player = $stats['player'];

        $statRecord = PlayerStatistic::firstOrCreate(
            [
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
            ],
            [
                'actual_team_id' => $stats['team_id'],
                'matches' => 0,
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
                'overs_bowled' => 0,
                'runs_conceded' => 0,
                'wickets' => 0,
                'maidens' => 0,
                'best_bowling' => null,
                'four_wickets' => 0,
                'five_wickets' => 0,
                'wides' => 0,
                'no_balls' => 0,
                'catches' => 0,
                'stumpings' => 0,
                'run_outs' => 0,
            ]
        );

        // Only count if bowled at least one ball
        if ($stats['balls_bowled'] > 0) {
            $statRecord->innings_bowled++;

            // Convert balls to overs (e.g., 25 balls = 4.1 overs)
            $completedOvers = floor($stats['balls_bowled'] / 6);
            $remainingBalls = $stats['balls_bowled'] % 6;
            $oversThisMatch = $completedOvers + ($remainingBalls / 10);

            $statRecord->overs_bowled += $oversThisMatch;
            $statRecord->runs_conceded += $stats['runs_conceded'];
            $statRecord->wickets += $stats['wickets'];
            $statRecord->wides += $stats['wides'];
            $statRecord->no_balls += $stats['no_balls'];

            // Count maidens (overs with 0 runs and 6 balls)
            $maidens = 0;
            foreach ($stats['overs'] as $over) {
                if ($over['balls'] >= 6 && $over['runs'] === 0) {
                    $maidens++;
                }
            }
            $statRecord->maidens += $maidens;

            // Update best bowling
            $thisBowling = $stats['wickets'] . '/' . $stats['runs_conceded'];
            if ($statRecord->best_bowling) {
                // Compare: more wickets is better, then fewer runs
                $currentParts = explode('/', $statRecord->best_bowling);
                $currentWickets = (int)$currentParts[0];
                $currentRuns = (int)$currentParts[1];

                if ($stats['wickets'] > $currentWickets ||
                    ($stats['wickets'] === $currentWickets && $stats['runs_conceded'] < $currentRuns)) {
                    $statRecord->best_bowling = $thisBowling;
                }
            } else {
                $statRecord->best_bowling = $thisBowling;
            }

            // Count 4-wicket and 5-wicket hauls
            if ($stats['wickets'] >= 5) {
                $statRecord->five_wickets++;
            } elseif ($stats['wickets'] >= 4) {
                $statRecord->four_wickets++;
            }
        }

        $statRecord->matches = max($statRecord->matches, $statRecord->innings_bowled, $statRecord->innings_batted);
        $statRecord->save();
    }

    /**
     * Process fielding statistics from balls
     */
    private function processFieldingStats(Collection $balls, Collection $teamUsers, Tournament $tournament): void
    {
        $fielderStats = [];

        foreach ($balls as $ball) {
            if (!$ball->is_wicket || !$ball->fielder_id) {
                continue;
            }

            $fielderId = $ball->fielder_id;
            if (!isset($teamUsers[$fielderId])) {
                continue;
            }

            $teamUser = $teamUsers[$fielderId];
            $player = $teamUser->player;

            if (!$player) {
                continue;
            }

            $playerId = $player->id;

            if (!isset($fielderStats[$playerId])) {
                $fielderStats[$playerId] = [
                    'player' => $player,
                    'team_id' => $teamUser->actual_team_id,
                    'catches' => 0,
                    'stumpings' => 0,
                    'run_outs' => 0,
                ];
            }

            // Categorize dismissal type
            switch ($ball->dismissal_type) {
                case 'caught':
                    $fielderStats[$playerId]['catches']++;
                    break;
                case 'stumped':
                    $fielderStats[$playerId]['stumpings']++;
                    break;
                case 'run_out':
                    $fielderStats[$playerId]['run_outs']++;
                    break;
            }
        }

        // Update database for each fielder
        foreach ($fielderStats as $playerId => $stats) {
            $this->updateFieldingStatistic($tournament, $stats);
        }
    }

    /**
     * Update fielding statistics for a player
     */
    private function updateFieldingStatistic(Tournament $tournament, array $stats): void
    {
        $player = $stats['player'];

        $statRecord = PlayerStatistic::firstOrCreate(
            [
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
            ],
            [
                'actual_team_id' => $stats['team_id'],
                'matches' => 0,
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
                'overs_bowled' => 0,
                'runs_conceded' => 0,
                'wickets' => 0,
                'maidens' => 0,
                'best_bowling' => null,
                'four_wickets' => 0,
                'five_wickets' => 0,
                'wides' => 0,
                'no_balls' => 0,
                'catches' => 0,
                'stumpings' => 0,
                'run_outs' => 0,
            ]
        );

        $statRecord->catches += $stats['catches'];
        $statRecord->stumpings += $stats['stumpings'];
        $statRecord->run_outs += $stats['run_outs'];
        $statRecord->save();
    }

    /**
     * Recalculate all statistics for a tournament
     */
    public function recalculateForTournament(Tournament $tournament): void
    {
        // Delete existing statistics
        PlayerStatistic::where('tournament_id', $tournament->id)->delete();

        // Get all completed matches
        $matches = $tournament->matches()
            ->where('status', 'completed')
            ->where('is_cancelled', false)
            ->get();

        foreach ($matches as $match) {
            $this->updateFromMatch($match);
        }
    }
}
