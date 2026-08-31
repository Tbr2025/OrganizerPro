<?php

namespace App\Services\Tournament;

use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\PointTableEntry;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Support\Collection;

class PointTableService
{
    /**
     * Initialize point table entries for all teams in a tournament
     */
    public function initializePointTable(Tournament $tournament): void
    {
        $settings = $tournament->settings;

        foreach ($tournament->groups as $group) {
            foreach ($group->teams as $team) {
                PointTableEntry::firstOrCreate([
                    'tournament_id' => $tournament->id,
                    'tournament_group_id' => $group->id,
                    'actual_team_id' => $team->id,
                ], [
                    'matches_played' => 0,
                    'won' => 0,
                    'lost' => 0,
                    'tied' => 0,
                    'no_result' => 0,
                    'points' => 0,
                    'runs_scored' => 0,
                    'overs_faced' => 0,
                    'runs_conceded' => 0,
                    'overs_bowled' => 0,
                    'net_run_rate' => 0,
                    'position' => 0,
                ]);
            }
        }
    }

    /**
     * Update point table after a match result is entered.
     *
     * Always does a full recalculation so that re-saving or editing a result
     * never double-counts matches.
     */
    public function updateFromMatchResult(Matches $match): void
    {
        $this->recalculatePointTable($match->tournament);
    }

    /**
     * Recalculate entire point table from scratch
     */
    public function recalculatePointTable(Tournament $tournament): void
    {
        // Reset all entries
        $tournament->pointTableEntries()->delete();

        // Re-initialize
        $this->initializePointTable($tournament);

        // Process all completed group-stage matches with results
        $matches = $tournament->matches()
            ->with('result')
            ->where('status', 'completed')
            ->where('is_cancelled', false)
            ->get();

        $settings = $tournament->settings;

        foreach ($matches as $match) {
            if ($match->result && $match->isGroupStage()) {
                $this->applyMatchResult($match, $settings);
            }
        }

        // Update all positions
        foreach ($tournament->groups as $group) {
            $this->updatePositions($tournament, $group->id);
        }
    }

    /**
     * Apply a single match result to the point table entries (incremental).
     * Only called from recalculatePointTable to avoid double-counting.
     */
    private function applyMatchResult(Matches $match, $settings): void
    {
        $result = $match->result;
        $tournament = $match->tournament;

        $teamAEntry = $this->getOrCreateEntry($tournament, $match->tournament_group_id, $match->team_a_id);
        $teamBEntry = $this->getOrCreateEntry($tournament, $match->tournament_group_id, $match->team_b_id);

        // Update Team A stats
        $teamAEntry->matches_played++;
        $teamAEntry->runs_scored += $result->team_a_score;
        $teamAEntry->overs_faced += $this->oversToDecimal($result->team_a_overs);
        $teamAEntry->runs_conceded += $result->team_b_score;
        $teamAEntry->overs_bowled += $this->oversToDecimal($result->team_b_overs);

        // Update Team B stats
        $teamBEntry->matches_played++;
        $teamBEntry->runs_scored += $result->team_b_score;
        $teamBEntry->overs_faced += $this->oversToDecimal($result->team_b_overs);
        $teamBEntry->runs_conceded += $result->team_a_score;
        $teamBEntry->overs_bowled += $this->oversToDecimal($result->team_a_overs);

        // Update win/loss/tie based on result
        if ($result->result_type === 'tie') {
            $teamAEntry->tied++;
            $teamBEntry->tied++;
            $teamAEntry->points += $settings->points_per_tie ?? 1;
            $teamBEntry->points += $settings->points_per_tie ?? 1;
        } elseif ($result->result_type === 'no_result') {
            $teamAEntry->no_result++;
            $teamBEntry->no_result++;
            $teamAEntry->points += $settings->points_per_no_result ?? 1;
            $teamBEntry->points += $settings->points_per_no_result ?? 1;
        } elseif ($result->winner_team_id === $match->team_a_id) {
            $teamAEntry->won++;
            $teamBEntry->lost++;
            $teamAEntry->points += $settings->points_per_win ?? 2;
            $teamBEntry->points += $settings->points_per_loss ?? 0;
        } elseif ($result->winner_team_id === $match->team_b_id) {
            $teamBEntry->won++;
            $teamAEntry->lost++;
            $teamBEntry->points += $settings->points_per_win ?? 2;
            $teamAEntry->points += $settings->points_per_loss ?? 0;
        }

        // Calculate NRR
        $teamAEntry->net_run_rate = $this->calculateNRR($teamAEntry);
        $teamBEntry->net_run_rate = $this->calculateNRR($teamBEntry);

        $teamAEntry->save();
        $teamBEntry->save();
    }

    /**
     * Get or create a point table entry
     */
    private function getOrCreateEntry(Tournament $tournament, ?int $groupId, int $teamId): PointTableEntry
    {
        return PointTableEntry::firstOrCreate([
            'tournament_id' => $tournament->id,
            'tournament_group_id' => $groupId,
            'actual_team_id' => $teamId,
        ], [
            'matches_played' => 0,
            'won' => 0,
            'lost' => 0,
            'tied' => 0,
            'no_result' => 0,
            'points' => 0,
        ]);
    }

    /**
     * Calculate Net Run Rate
     */
    private function calculateNRR(PointTableEntry $entry): float
    {
        $runRateFor = $entry->overs_faced > 0
            ? $entry->runs_scored / $entry->overs_faced
            : 0;

        $runRateAgainst = $entry->overs_bowled > 0
            ? $entry->runs_conceded / $entry->overs_bowled
            : 0;

        return round($runRateFor - $runRateAgainst, 3);
    }

    /**
     * Convert overs (e.g., 19.4) to decimal
     */
    private function oversToDecimal(float $overs): float
    {
        $wholeOvers = floor($overs);
        $balls = ($overs - $wholeOvers) * 10;

        return $wholeOvers + ($balls / 6);
    }

    /**
     * Update positions for a group
     */
    public function updatePositions(Tournament $tournament, ?int $groupId): void
    {
        $entries = PointTableEntry::where('tournament_id', $tournament->id)
            ->where('tournament_group_id', $groupId)
            ->orderByDesc('points')
            ->orderByDesc('net_run_rate')
            ->orderByDesc('won')
            ->get();

        $position = 1;
        foreach ($entries as $entry) {
            $entry->position = $position;
            $entry->qualified = $position <= 2; // Top 2 qualify by default
            $entry->save();
            $position++;
        }
    }

    /**
     * Get point table for a tournament/group
     */
    public function getPointTable(Tournament $tournament, ?int $groupId = null): Collection
    {
        $query = PointTableEntry::with('team')
            ->where('tournament_id', $tournament->id);

        if ($groupId) {
            $query->where('tournament_group_id', $groupId);
        }

        return $query->ranked()->get();
    }

    /**
     * Get point table grouped by groups
     * Returns a collection keyed by group name with entries as values
     */
    public function getPointTableByGroups(Tournament $tournament): Collection
    {
        // If no groups, return single "default" entry
        if ($tournament->groups->isEmpty()) {
            return collect(['default' => $this->getPointTable($tournament)]);
        }

        return $tournament->groups->mapWithKeys(function ($group) use ($tournament) {
            return [$group->name => $this->getPointTable($tournament, $group->id)];
        });
    }
}
