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
    /** Match stages that make up a league/group phase, as opposed to a knockout round. */
    public const LEAGUE_STAGES = ['league', 'group'];

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
        // "Qualified" is the organizer's call (Mark Qualified Teams), not something a result
        // implies, so it survives the rebuild instead of being wiped with the rows.
        $qualified = $tournament->pointTableEntries()
            ->where('qualified', true)
            ->get(['tournament_group_id', 'actual_team_id'])
            ->map(fn ($e) => $e->tournament_group_id . ':' . $e->actual_team_id)
            ->all();

        // Reset all entries
        $tournament->pointTableEntries()->delete();

        // Re-initialize
        $this->initializePointTable($tournament);

        if ($qualified) {
            $tournament->pointTableEntries()->get()
                ->filter(fn ($e) => in_array($e->tournament_group_id . ':' . $e->actual_team_id, $qualified, true))
                ->each(fn ($e) => $e->update(['qualified' => true]));
        }

        // Process all completed group-stage matches with results
        $matches = $tournament->matches()
            ->with('result')
            ->where('status', 'completed')
            ->where('is_cancelled', false)
            ->get();

        $settings = $tournament->settings;

        // Balls faced/bowled per entry id, summed as integers. The overs columns are decimals,
        // so adding a third of an over to them match by match rounds every time; NRR is worked
        // out from these exact totals instead.
        $balls = [];

        foreach ($matches as $match) {
            if ($match->result && $match->isGroupStage()) {
                $this->applyMatchResult($match, $settings, $balls);
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
    private function applyMatchResult(Matches $match, $settings, array &$balls): void
    {
        $result = $match->result;
        $tournament = $match->tournament;

        $teamAEntry = $this->getOrCreateEntry($tournament, $match->tournament_group_id, $match->team_a_id);
        $teamBEntry = $this->getOrCreateEntry($tournament, $match->tournament_group_id, $match->team_b_id);

        $quota = (int) ($match->overs ?: ($settings->overs_per_match ?? 20));
        $teamABalls = $this->ballsForNrr($result->team_a_overs, $result->team_a_wickets, $quota);
        $teamBBalls = $this->ballsForNrr($result->team_b_overs, $result->team_b_wickets, $quota);

        $balls[$teamAEntry->id]['faced'] = ($balls[$teamAEntry->id]['faced'] ?? 0) + $teamABalls;
        $balls[$teamAEntry->id]['bowled'] = ($balls[$teamAEntry->id]['bowled'] ?? 0) + $teamBBalls;
        $balls[$teamBEntry->id]['faced'] = ($balls[$teamBEntry->id]['faced'] ?? 0) + $teamBBalls;
        $balls[$teamBEntry->id]['bowled'] = ($balls[$teamBEntry->id]['bowled'] ?? 0) + $teamABalls;

        // Update Team A stats
        $teamAEntry->matches_played++;
        $teamAEntry->runs_scored += $result->team_a_score;
        $teamAEntry->overs_faced = $balls[$teamAEntry->id]['faced'] / 6;
        $teamAEntry->runs_conceded += $result->team_b_score;
        $teamAEntry->overs_bowled = $balls[$teamAEntry->id]['bowled'] / 6;

        // Update Team B stats
        $teamBEntry->matches_played++;
        $teamBEntry->runs_scored += $result->team_b_score;
        $teamBEntry->overs_faced = $balls[$teamBEntry->id]['faced'] / 6;
        $teamBEntry->runs_conceded += $result->team_a_score;
        $teamBEntry->overs_bowled = $balls[$teamBEntry->id]['bowled'] / 6;

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
        $teamAEntry->net_run_rate = $this->calculateNRR($teamAEntry, $balls[$teamAEntry->id]);
        $teamBEntry->net_run_rate = $this->calculateNRR($teamBEntry, $balls[$teamBEntry->id]);

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
    private function calculateNRR(PointTableEntry $entry, array $balls): float
    {
        $runRateFor = ($balls['faced'] ?? 0) > 0
            ? $entry->runs_scored * 6 / $balls['faced']
            : 0;

        $runRateAgainst = ($balls['bowled'] ?? 0) > 0
            ? $entry->runs_conceded * 6 / $balls['bowled']
            : 0;

        return round($runRateFor - $runRateAgainst, 3);
    }

    /**
     * The balls an innings counts for in NRR.
     *
     * A side that is bowled out is charged its full quota, not the overs it lasted (the ICC
     * rule, and what CricHeroes shows). Without this, 92 all out in 14.5 overs reads as a run
     * rate of 6.20 instead of 4.60 — flattering the side that collapsed and short-changing the
     * side that bowled it out, which moved teams on the same points past each other.
     *
     * All out is taken as 10 wickets: there is no players-per-side setting to read a smaller
     * figure from.
     */
    private function ballsForNrr($overs, $wickets, int $quota): int
    {
        if ((int) $wickets >= 10 && $quota > 0) {
            return $quota * 6;
        }

        return $this->oversToBalls((float) $overs);
    }

    /**
     * Convert cricket overs notation (19.4 = 19 overs and 4 balls) to balls.
     */
    private function oversToBalls(float $overs): int
    {
        $wholeOvers = (int) floor($overs);
        // round(): 19.4 - 19 is 0.3999…, which would truncate to 3 balls.
        $balls = (int) round(($overs - $wholeOvers) * 10);

        return $wholeOvers * 6 + $balls;
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
            // Qualification is not set here: marking the top two after every result told the
            // table, the posters and the admin page that sides were through after one round.
            $entry->save();
            $position++;
        }
    }

    /**
     * Has this group's league stage actually finished?
     *
     * `qualified` is set by hand on the admin page (it used to be "currently top two", set by
     * updatePositions() after every result). Posters still only draw it once no league fixture
     * in the group is left to play; the public page no longer shows it at all.
     *
     * League stages here are recorded as `league` or `group`; knockout rounds have their own
     * stages and are never counted. A group with no league fixtures at all has decided nothing.
     */
    public function qualificationDecided(Tournament $tournament, ?int $groupId = null): bool
    {
        $query = Matches::where('tournament_id', $tournament->id)
            ->whereIn('stage', self::LEAGUE_STAGES);

        if ($groupId !== null) {
            $query->where('tournament_group_id', $groupId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            return false;
        }

        // `status` is only upcoming/live/completed — a called-off fixture is flagged rather than
        // given a status of its own, and it is never going to be played, so it does not hold the
        // table open.
        $pending = (clone $query)
            ->where('status', '!=', 'completed')
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->count();

        return $pending === 0;
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
