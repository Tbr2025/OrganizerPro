<?php

namespace App\Services\Tournament;

use App\Models\Ground;
use App\Models\Matches;
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
        $grounds = Ground::where('organization_id', $tournament->organization_id)
            ->active()
            ->limit($settings->number_of_grounds ?? 1)
            ->get();

        $fixtures = collect();
        $matchNumber = 1;
        $currentDate = Carbon::parse($tournament->start_date);
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

                $groundIndex = $matchesScheduledThisWeek % $grounds->count();
                $ground = $grounds[$groundIndex] ?? $grounds->first();

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

        $grounds = Ground::where('organization_id', $tournament->organization_id)
            ->active()
            ->get();

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
     * Generate league fixtures (all teams play each other once/twice)
     */
    public function generateLeagueFixtures(Tournament $tournament, bool $homeAndAway = false): Collection
    {
        $settings = $tournament->settings;
        $teams = $tournament->actualTeams;
        $grounds = Ground::where('organization_id', $tournament->organization_id)
            ->active()
            ->get();

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
