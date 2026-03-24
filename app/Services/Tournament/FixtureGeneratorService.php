<?php

namespace App\Services\Tournament;

use App\Models\ActualTeam;
use App\Models\Ground;
use App\Models\Matches;
use App\Models\PointTableEntry;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FixtureGeneratorService
{
    /**
     * Generate round-robin fixtures for group stage
     */
    public function generateGroupStageFixtures(Tournament $tournament): Collection
    {
        $settings = $tournament->settings;
        $groups = $tournament->groups()->with('teams')->get();

        if ($groups->isEmpty()) {
            throw new \RuntimeException('No groups found. Please create groups and add teams first.');
        }

        $grounds = Ground::where('organization_id', $tournament->organization_id)
            ->active()
            ->limit($settings->number_of_grounds ?? 1)
            ->get();

        $fixtures = collect();
        $matchNumber = 1;
        $currentDate = $tournament->start_date
            ? Carbon::parse($tournament->start_date)
            : Carbon::now()->addDays(7)->startOfWeek();
        $matchesPerWeek = $settings->matches_per_week ?? 4;
        $matchesScheduledThisWeek = 0;

        foreach ($groups as $group) {
            $teams = $group->teams;
            $teamCount = $teams->count();

            if ($teamCount < 2) {
                continue;
            }

            // Generate round-robin pairs
            $pairs = $this->generateRoundRobinPairs($teams->pluck('id')->toArray());

            foreach ($pairs as $pair) {
                // Check if we need to move to next week
                if ($matchesScheduledThisWeek >= $matchesPerWeek) {
                    $currentDate->addWeek()->startOfWeek();
                    $matchesScheduledThisWeek = 0;
                }

                $ground = null;
                if ($grounds->isNotEmpty()) {
                    $groundIndex = $matchesScheduledThisWeek % $grounds->count();
                    $ground = $grounds[$groundIndex] ?? $grounds->first();
                }

                $teamA = $teams->firstWhere('id', $pair[0]);
                $teamB = $teams->firstWhere('id', $pair[1]);

                $match = Matches::create([
                    'tournament_id' => $tournament->id,
                    'tournament_group_id' => $group->id,
                    'name' => "Match {$matchNumber}: {$teamA->name} vs {$teamB->name}",
                    'slug' => Str::slug("match-{$matchNumber}-" . Str::random(6)),
                    'team_a_id' => $pair[0],
                    'team_b_id' => $pair[1],
                    'match_date' => $currentDate->copy(),
                    'ground_id' => $ground?->id,
                    'venue' => $ground?->name ?? $tournament->location,
                    'stage' => 'group',
                    'match_number' => $matchNumber,
                    'status' => 'upcoming',
                    'overs' => $settings->overs_per_match ?? 20,
                    'start_time' => '09:00',
                    'end_time' => '13:00',
                ]);

                $fixtures->push($match);
                $matchNumber++;
                $matchesScheduledThisWeek++;

                // Move to next day for the next match
                if ($matchesScheduledThisWeek < $matchesPerWeek) {
                    $currentDate->addDay();
                    // Skip weekends if needed (optional)
                    while ($currentDate->isWeekend()) {
                        $currentDate->addDay();
                    }
                }
            }
        }

        return $fixtures;
    }

    /**
     * Generate round-robin pairs using circle method
     */
    private function generateRoundRobinPairs(array $teams): array
    {
        $n = count($teams);
        $pairs = [];

        // If odd number of teams, add a "bye"
        if ($n % 2 !== 0) {
            $teams[] = null;
            $n++;
        }

        $rounds = $n - 1;
        $matchesPerRound = $n / 2;

        for ($round = 0; $round < $rounds; $round++) {
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = ($round + $match) % ($n - 1);
                $away = ($n - 1 - $match + $round) % ($n - 1);

                if ($match === 0) {
                    $away = $n - 1;
                }

                $teamA = $teams[$home];
                $teamB = $teams[$away];

                // Skip if either team is a bye
                if ($teamA !== null && $teamB !== null) {
                    $pairs[] = [$teamA, $teamB];
                }
            }
        }

        return $pairs;
    }

    /**
     * Generate knockout stage fixtures
     */
    public function generateKnockoutFixtures(Tournament $tournament, string $stage): Collection
    {
        $settings = $tournament->settings;
        $fixtures = collect();

        $existingMatches = $tournament->matches()->max('match_number') ?? 0;
        $matchNumber = $existingMatches + 1;

        $grounds = Ground::where(function ($q) use ($tournament) {
            $q->where('organization_id', $tournament->organization_id)
              ->orWhereNull('organization_id');
        })->active()->get();

        $teamsForStage = $this->getTeamsForKnockoutStage($tournament, $stage);
        $matchCount = count($teamsForStage) / 2;

        // Calculate start date based on last group stage match
        $lastMatch = $tournament->matches()->orderByDesc('match_date')->first();
        $startDate = $lastMatch
            ? Carbon::parse($lastMatch->match_date)->addDays(3)
            : Carbon::parse($tournament->start_date);

        for ($i = 0; $i < $matchCount; $i++) {
            $teamAId = $teamsForStage[$i * 2] ?? null;
            $teamBId = $teamsForStage[$i * 2 + 1] ?? null;

            $stageName = $this->getStageName($stage, $i + 1, $matchCount);
            $groundIndex = $i % $grounds->count();

            $match = Matches::create([
                'tournament_id' => $tournament->id,
                'name' => $stageName,
                'slug' => Str::slug($stageName . '-' . Str::random(6)),
                'team_a_id' => $teamAId,
                'team_b_id' => $teamBId,
                'match_date' => $startDate->copy()->addDays($i),
                'ground_id' => $grounds[$groundIndex]?->id,
                'venue' => $grounds[$groundIndex]?->name ?? $tournament->location,
                'stage' => $stage,
                'match_number' => $matchNumber++,
                'status' => 'upcoming',
                'overs' => $settings->overs_per_match ?? 20,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);

            $fixtures->push($match);
        }

        return $fixtures;
    }

    /**
     * Get teams for knockout stage based on point table standings
     */
    private function getTeamsForKnockoutStage(Tournament $tournament, string $stage): array
    {
        $settings = $tournament->settings;

        if ($stage === 'quarter_final') {
            // Top 2 from each of 4 groups
            return $this->getTopTeamsFromGroups($tournament, 2);
        }

        if ($stage === 'semi_final') {
            // If coming after quarter finals, get winners
            $quarterFinals = $tournament->matches()->where('stage', 'quarter_final')->get();
            if ($quarterFinals->count() > 0) {
                return $quarterFinals->pluck('winner_team_id')->filter()->toArray();
            }
            // Otherwise get top 2 from each of 2 groups
            return $this->getTopTeamsFromGroups($tournament, 2);
        }

        if ($stage === 'final') {
            $semiFinals = $tournament->matches()->where('stage', 'semi_final')->get();
            return $semiFinals->pluck('winner_team_id')->filter()->toArray();
        }

        if ($stage === 'third_place') {
            // Losers of semi-finals
            $semiFinals = $tournament->matches()->where('stage', 'semi_final')->get();
            $losers = [];
            foreach ($semiFinals as $match) {
                $loser = $match->team_a_id === $match->winner_team_id
                    ? $match->team_b_id
                    : $match->team_a_id;
                $losers[] = $loser;
            }
            return $losers;
        }

        return [];
    }

    /**
     * Get top N teams from each group
     */
    private function getTopTeamsFromGroups(Tournament $tournament, int $topN): array
    {
        $teams = [];

        foreach ($tournament->groups as $group) {
            $topTeams = $group->pointTableEntries()
                ->ranked()
                ->limit($topN)
                ->pluck('actual_team_id')
                ->toArray();

            $teams = array_merge($teams, $topTeams);
        }

        return $teams;
    }

    /**
     * Get stage name for a knockout match
     */
    private function getStageName(string $stage, int $matchIndex, int $totalMatches): string
    {
        return match ($stage) {
            'quarter_final' => "Quarter Final {$matchIndex}",
            'semi_final' => "Semi Final {$matchIndex}",
            'final' => "Final",
            'third_place' => "3rd Place Playoff",
            'qualifier_1' => "Qualifier 1",
            'eliminator' => "Eliminator",
            'qualifier_2' => "Qualifier 2",
            default => ucfirst(str_replace('_', ' ', $stage)) . " {$matchIndex}",
        };
    }

    /**
     * Reschedule a match
     */
    public function rescheduleMatch(Matches $match, Carbon $newDate, ?Ground $ground = null): bool
    {
        $updates = [
            'match_date' => $newDate,
        ];

        if ($ground) {
            $updates['ground_id'] = $ground->id;
            $updates['venue'] = $ground->name;
        }

        return $match->update($updates);
    }

    /**
     * Cancel a match
     */
    public function cancelMatch(Matches $match, ?string $reason = null): bool
    {
        return $match->update([
            'is_cancelled' => true,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Create a single custom match
     */
    public function createCustomMatch(Tournament $tournament, array $data): Matches
    {
        $existingMax = $tournament->matches()->max('match_number') ?? 0;
        $matchNumber = $existingMax + 1;

        $teamA = ActualTeam::find($data['team_a_id']);
        $teamB = ActualTeam::find($data['team_b_id']);
        $teamAName = $teamA?->name ?? 'TBD';
        $teamBName = $teamB?->name ?? 'TBD';

        $ground = isset($data['ground_id']) ? Ground::find($data['ground_id']) : null;
        $venue = $data['venue'] ?? $ground?->name ?? $tournament->location;

        return Matches::create([
            'tournament_id' => $tournament->id,
            'tournament_group_id' => $data['group_id'] ?? null,
            'name' => "Match {$matchNumber}: {$teamAName} vs {$teamBName}",
            'slug' => Str::slug("match-{$matchNumber}-" . Str::random(6)),
            'team_a_id' => $data['team_a_id'],
            'team_b_id' => $data['team_b_id'],
            'match_date' => $data['date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'ground_id' => $ground?->id,
            'venue' => $venue,
            'stage' => $data['stage'] ?? 'group',
            'match_number' => $matchNumber,
            'status' => 'upcoming',
            'overs' => $data['overs'] ?? $tournament->settings->overs_per_match ?? 20,
        ]);
    }

    /**
     * Generate IPL-style playoff fixtures (Q1, Eliminator, Q2, Final)
     */
    public function generateIplPlayoffs(Tournament $tournament): Collection
    {
        $fixtures = collect();
        $existingMax = $tournament->matches()->max('match_number') ?? 0;
        $matchNumber = $existingMax + 1;

        $settings = $tournament->settings;
        $grounds = Ground::where(function ($q) use ($tournament) {
            $q->where('organization_id', $tournament->organization_id)
              ->orWhereNull('organization_id');
        })->active()->get();

        $ground = $grounds->first();

        // Get top 4 teams from unified point table
        $topTeams = $this->getTopTeamsFromPointTable($tournament, 4);

        // Calculate start date
        $lastMatch = $tournament->matches()->orderByDesc('match_date')->first();
        $startDate = $lastMatch
            ? Carbon::parse($lastMatch->match_date)->addDays(3)
            : Carbon::parse($tournament->start_date);

        $playoffConfig = [
            [
                'stage' => 'qualifier_1',
                'name' => 'Qualifier 1',
                'team_a' => $topTeams[0] ?? null,
                'team_b' => $topTeams[1] ?? null,
                'day_offset' => 0,
            ],
            [
                'stage' => 'eliminator',
                'name' => 'Eliminator',
                'team_a' => $topTeams[2] ?? null,
                'team_b' => $topTeams[3] ?? null,
                'day_offset' => 1,
            ],
            [
                'stage' => 'qualifier_2',
                'name' => 'Qualifier 2',
                'team_a' => null, // Loser of Q1
                'team_b' => null, // Winner of Eliminator
                'day_offset' => 3,
            ],
            [
                'stage' => 'final',
                'name' => 'Final',
                'team_a' => null, // Winner of Q1
                'team_b' => null, // Winner of Q2
                'day_offset' => 5,
            ],
        ];

        foreach ($playoffConfig as $config) {
            $teamA = $config['team_a'] ? ActualTeam::find($config['team_a']) : null;
            $teamB = $config['team_b'] ? ActualTeam::find($config['team_b']) : null;
            $teamAName = $teamA?->name ?? 'TBD';
            $teamBName = $teamB?->name ?? 'TBD';

            $matchName = "{$config['name']}: {$teamAName} vs {$teamBName}";

            $match = Matches::create([
                'tournament_id' => $tournament->id,
                'name' => $matchName,
                'slug' => Str::slug($config['name'] . '-' . Str::random(6)),
                'team_a_id' => $config['team_a'],
                'team_b_id' => $config['team_b'],
                'match_date' => $startDate->copy()->addDays($config['day_offset']),
                'ground_id' => $ground?->id,
                'venue' => $ground?->name ?? $tournament->location,
                'stage' => $config['stage'],
                'match_number' => $matchNumber++,
                'status' => 'upcoming',
                'overs' => $settings->overs_per_match ?? 20,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);

            $fixtures->push($match);
        }

        return $fixtures;
    }

    /**
     * Update match details
     */
    public function updateMatch(Matches $match, array $data): bool
    {
        $updates = [];

        if (isset($data['team_a_id'])) {
            $updates['team_a_id'] = $data['team_a_id'];
        }
        if (isset($data['team_b_id'])) {
            $updates['team_b_id'] = $data['team_b_id'];
        }
        if (array_key_exists('date', $data)) {
            $updates['match_date'] = $data['date'];
        }
        if (array_key_exists('start_time', $data)) {
            $updates['start_time'] = $data['start_time'];
        }
        if (isset($data['stage'])) {
            $updates['stage'] = $data['stage'];
        }
        if (isset($data['venue'])) {
            $updates['venue'] = $data['venue'];
            $updates['ground_id'] = null;
        } elseif (isset($data['ground_id'])) {
            $ground = Ground::find($data['ground_id']);
            $updates['ground_id'] = $ground?->id;
            $updates['venue'] = $ground?->name;
        }
        if (isset($data['overs'])) {
            $updates['overs'] = $data['overs'];
        }
        if (isset($data['group_id'])) {
            $updates['tournament_group_id'] = $data['group_id'];
        }

        // Auto-regenerate match name if teams changed
        if (isset($updates['team_a_id']) || isset($updates['team_b_id'])) {
            $teamAId = $updates['team_a_id'] ?? $match->team_a_id;
            $teamBId = $updates['team_b_id'] ?? $match->team_b_id;
            $teamA = ActualTeam::find($teamAId);
            $teamB = ActualTeam::find($teamBId);
            $updates['name'] = "Match {$match->match_number}: " . ($teamA?->name ?? 'TBD') . " vs " . ($teamB?->name ?? 'TBD');
        }

        // Reset poster_sent if schedule or teams changed
        if (isset($updates['match_date']) || isset($updates['team_a_id']) || isset($updates['team_b_id'])) {
            $updates['poster_sent'] = false;
            $updates['poster_sent_at'] = null;
        }

        return $match->update($updates);
    }

    /**
     * Delete a match and release its time slot
     */
    public function deleteMatch(Matches $match): bool
    {
        if ($match->timeSlot) {
            $match->timeSlot->releaseMatch();
        }

        return $match->delete();
    }

    /**
     * Get top N teams from unified point table (across all groups)
     */
    private function getTopTeamsFromPointTable(Tournament $tournament, int $topN): array
    {
        return PointTableEntry::where('tournament_id', $tournament->id)
            ->orderByDesc('points')
            ->orderByDesc('net_run_rate')
            ->orderByDesc('won')
            ->limit($topN)
            ->pluck('actual_team_id')
            ->toArray();
    }

    /**
     * Generate league fixtures (all teams play each other once/twice)
     */
    public function generateLeagueFixtures(Tournament $tournament, bool $homeAndAway = false): Collection
    {
        $settings = $tournament->settings;
        $teams = $tournament->actualTeams;
        $grounds = Ground::where(function ($q) use ($tournament) {
            $q->where('organization_id', $tournament->organization_id)
              ->orWhereNull('organization_id');
        })->active()->get();

        $fixtures = collect();
        $matchNumber = 1;
        $currentDate = Carbon::parse($tournament->start_date);
        $matchesPerWeek = $settings->matches_per_week ?? 4;
        $matchesScheduledThisWeek = 0;

        $pairs = $this->generateRoundRobinPairs($teams->pluck('id')->toArray());

        // If home and away, duplicate pairs with reversed order
        if ($homeAndAway) {
            $reversedPairs = array_map(fn($pair) => [$pair[1], $pair[0]], $pairs);
            $pairs = array_merge($pairs, $reversedPairs);
        }

        foreach ($pairs as $pair) {
            if ($matchesScheduledThisWeek >= $matchesPerWeek) {
                $currentDate->addWeek()->startOfWeek();
                $matchesScheduledThisWeek = 0;
            }

            $groundIndex = $matchesScheduledThisWeek % $grounds->count();
            $ground = $grounds[$groundIndex] ?? $grounds->first();

            $teamA = $teams->firstWhere('id', $pair[0]);
            $teamB = $teams->firstWhere('id', $pair[1]);

            $match = Matches::create([
                'tournament_id' => $tournament->id,
                'name' => "Match {$matchNumber}: {$teamA->name} vs {$teamB->name}",
                'slug' => Str::slug("match-{$matchNumber}-" . Str::random(6)),
                'team_a_id' => $pair[0],
                'team_b_id' => $pair[1],
                'match_date' => $currentDate->copy(),
                'ground_id' => $ground?->id,
                'venue' => $ground?->name ?? $tournament->location,
                'stage' => 'league',
                'match_number' => $matchNumber,
                'status' => 'upcoming',
                'overs' => $settings->overs_per_match ?? 20,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);

            $fixtures->push($match);
            $matchNumber++;
            $matchesScheduledThisWeek++;

            if ($matchesScheduledThisWeek < $matchesPerWeek) {
                $currentDate->addDay();
            }
        }

        return $fixtures;
    }
}
