<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Models\Matches;
use App\Models\MatchTimeSlot;
use App\Models\Ground;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarFixtureService
{
    /**
     * Generate time slots for a tournament based on date range and settings
     */
    public function generateTimeSlots(
        Tournament $tournament,
        Carbon $startDate,
        Carbon $endDate,
        array $timeSlots = [],
        ?array $groundIds = null,
        ?array $availableDays = null
    ): Collection {
        $settings = $tournament->settings;

        // Use settings defaults if not provided
        $timeSlots = $timeSlots ?: $settings->default_time_slots ?? [
            ['start' => '09:00', 'end' => '13:00'],
            ['start' => '14:00', 'end' => '18:00'],
        ];

        $availableDays = $availableDays ?? $settings->available_days ?? [0, 1, 2, 3, 4, 5, 6];

        // Get grounds
        $grounds = $groundIds
            ? Ground::whereIn('id', $groundIds)->get()
            : Ground::where('organization_id', $tournament->organization_id)->where('is_active', true)->get();

        if ($grounds->isEmpty()) {
            // Create a "default" entry with null ground
            $grounds = collect([null]);
        }

        $createdSlots = collect();
        $period = CarbonPeriod::create($startDate, $endDate);

        DB::beginTransaction();

        try {
            foreach ($period as $date) {
                // Check if day of week is available
                if (!in_array($date->dayOfWeek, $availableDays)) {
                    continue;
                }

                foreach ($grounds as $ground) {
                    foreach ($timeSlots as $slot) {
                        // Check if slot already exists
                        $exists = MatchTimeSlot::where('tournament_id', $tournament->id)
                            ->where('ground_id', $ground?->id)
                            ->whereDate('slot_date', $date)
                            ->where('start_time', $slot['start'])
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $newSlot = MatchTimeSlot::create([
                            'tournament_id' => $tournament->id,
                            'ground_id' => $ground?->id,
                            'slot_date' => $date,
                            'start_time' => $slot['start'],
                            'end_time' => $slot['end'],
                            'is_available' => true,
                        ]);

                        $createdSlots->push($newSlot);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $createdSlots;
    }

    /**
     * Auto-fill matches into available time slots
     * Uses intelligent algorithm to avoid same-day conflicts
     */
    public function autoFillMatches(Tournament $tournament): array
    {
        // Get unscheduled matches ordered by priority
        $matches = $tournament->matches()
            ->whereNull('match_date')
            ->where('is_cancelled', false)
            ->orderByRaw("FIELD(stage, 'group', 'league', 'quarter_final', 'semi_final', 'third_place', 'final')")
            ->orderBy('match_number')
            ->get();

        // Get available slots
        $slots = $tournament->timeSlots()
            ->available()
            ->upcoming()
            ->orderByDateTime()
            ->get();

        $assigned = [];
        $teamsPlayingOnDate = []; // Track which teams play on which dates

        foreach ($matches as $match) {
            $teamAId = $match->team_a_id;
            $teamBId = $match->team_b_id;

            // Skip if teams not assigned yet
            if (!$teamAId || !$teamBId) {
                continue;
            }

            foreach ($slots as $slot) {
                // Skip if slot already assigned
                if ($slot->match_id) {
                    continue;
                }

                $date = $slot->slot_date->format('Y-m-d');
                $teamsOnDate = $teamsPlayingOnDate[$date] ?? [];

                // Check if either team is already playing on this date
                if (in_array($teamAId, $teamsOnDate) || in_array($teamBId, $teamsOnDate)) {
                    continue;
                }

                // Assign match to slot
                $slot->assignMatch($match);

                // Track teams playing on this date
                $teamsPlayingOnDate[$date][] = $teamAId;
                $teamsPlayingOnDate[$date][] = $teamBId;

                $assigned[] = [
                    'match_id' => $match->id,
                    'slot_id' => $slot->id,
                    'date' => $date,
                    'time' => $slot->time_range,
                    'ground' => $slot->ground?->name ?? 'TBD',
                ];

                break;
            }
        }

        return $assigned;
    }

    /**
     * Get available slots for rescheduling
     */
    public function getAvailableSlots(Tournament $tournament, ?Carbon $fromDate = null): Collection
    {
        $fromDate = $fromDate ?? now();

        return $tournament->timeSlots()
            ->available()
            ->where('slot_date', '>=', $fromDate->toDateString())
            ->orderByDateTime()
            ->with('ground')
            ->get();
    }

    /**
     * Reschedule a match to a new slot
     */
    public function rescheduleMatch(Matches $match, MatchTimeSlot $newSlot): bool
    {
        if (!$newSlot->isAvailable()) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Release old slot if exists
            $oldSlot = $match->timeSlot;
            if ($oldSlot) {
                $oldSlot->releaseMatch();
            }

            // Assign to new slot
            $newSlot->assignMatch($match);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a match and release its slot
     */
    public function cancelMatch(Matches $match, ?string $reason = null): bool
    {
        DB::beginTransaction();

        try {
            $match->cancel($reason);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get calendar data for a tournament (for frontend calendar display)
     */
    public function getCalendarData(Tournament $tournament, Carbon $startDate, Carbon $endDate): array
    {
        $slots = $tournament->timeSlots()
            ->whereBetween('slot_date', [$startDate, $endDate])
            ->with(['ground', 'match.teamA', 'match.teamB'])
            ->orderByDateTime()
            ->get();

        $calendarData = [];

        foreach ($slots as $slot) {
            $date = $slot->slot_date->format('Y-m-d');

            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [];
            }

            $calendarData[$date][] = [
                'id' => $slot->id,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'time_range' => $slot->time_range,
                'ground' => $slot->ground ? [
                    'id' => $slot->ground->id,
                    'name' => $slot->ground->name,
                ] : null,
                'is_available' => $slot->isAvailable(),
                'match' => $slot->match ? [
                    'id' => $slot->match->id,
                    'team_a' => $slot->match->teamA?->name ?? 'TBD',
                    'team_b' => $slot->match->teamB?->name ?? 'TBD',
                    'stage' => $slot->match->stage,
                    'stage_display' => $slot->match->stage_display,
                    'status' => $slot->match->status,
                ] : null,
                'notes' => $slot->notes,
            ];
        }

        return $calendarData;
    }

    /**
     * Get unscheduled matches for a tournament
     */
    public function getUnscheduledMatches(Tournament $tournament): Collection
    {
        return $tournament->matches()
            ->whereNull('match_date')
            ->where('is_cancelled', false)
            ->with(['teamA', 'teamB'])
            ->orderByRaw("FIELD(stage, 'group', 'league', 'quarter_final', 'semi_final', 'third_place', 'final')")
            ->orderBy('match_number')
            ->get()
            ->map(function ($match) {
                return [
                    'id' => $match->id,
                    'match_number' => $match->match_number,
                    'team_a' => $match->teamA?->name ?? 'TBD',
                    'team_b' => $match->teamB?->name ?? 'TBD',
                    'stage' => $match->stage,
                    'stage_display' => $match->stage_display,
                ];
            });
    }

    /**
     * Delete time slots for a date range
     */
    public function deleteTimeSlots(Tournament $tournament, Carbon $startDate, Carbon $endDate, bool $onlyAvailable = true): int
    {
        $query = $tournament->timeSlots()
            ->whereBetween('slot_date', [$startDate, $endDate]);

        if ($onlyAvailable) {
            $query->available();
        }

        return $query->delete();
    }

    /**
     * Get scheduling statistics for a tournament
     */
    public function getSchedulingStats(Tournament $tournament): array
    {
        $totalMatches = $tournament->matches()->where('is_cancelled', false)->count();
        $scheduledMatches = $tournament->matches()
            ->where('is_cancelled', false)
            ->whereNotNull('match_date')
            ->count();
        $totalSlots = $tournament->timeSlots()->count();
        $availableSlots = $tournament->timeSlots()->available()->count();
        $upcomingSlots = $tournament->timeSlots()->available()->upcoming()->count();

        return [
            'total_matches' => $totalMatches,
            'scheduled_matches' => $scheduledMatches,
            'unscheduled_matches' => $totalMatches - $scheduledMatches,
            'total_slots' => $totalSlots,
            'available_slots' => $availableSlots,
            'occupied_slots' => $totalSlots - $availableSlots,
            'upcoming_available_slots' => $upcomingSlots,
            'scheduling_progress' => $totalMatches > 0
                ? round(($scheduledMatches / $totalMatches) * 100, 1)
                : 0,
        ];
    }
}
