<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\MatchTimeSlot;
use App\Models\Matches;
use App\Services\Tournament\CalendarFixtureService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TimeSlotController extends Controller
{
    protected CalendarFixtureService $calendarService;

    public function __construct(CalendarFixtureService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Get all time slots for a tournament
     */
    public function index(Tournament $tournament, Request $request): JsonResponse
    {
        $query = $tournament->timeSlots()->with(['ground', 'match.teamA', 'match.teamB']);

        // Filter by date range
        if ($request->has('start') && $request->has('end')) {
            $query->whereBetween('slot_date', [$request->get('start'), $request->get('end')]);
        }

        // Filter by availability
        if ($request->has('available')) {
            if ($request->boolean('available')) {
                $query->available();
            } else {
                $query->occupied();
            }
        }

        // Filter by ground
        if ($request->has('ground_id')) {
            $query->where('ground_id', $request->get('ground_id'));
        }

        $slots = $query->orderByDateTime()->get();

        return response()->json([
            'success' => true,
            'data' => $slots->map(function ($slot) {
                return $this->formatSlot($slot);
            }),
        ]);
    }

    /**
     * Create a new time slot
     */
    public function store(Tournament $tournament, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ground_id' => 'nullable|exists:grounds,id',
            'slot_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check for duplicate
        $exists = MatchTimeSlot::where('tournament_id', $tournament->id)
            ->where('ground_id', $validated['ground_id'])
            ->whereDate('slot_date', $validated['slot_date'])
            ->where('start_time', $validated['start_time'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A slot already exists for this date, time, and ground.',
            ], 422);
        }

        $slot = $tournament->timeSlots()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatSlot($slot->load(['ground', 'match.teamA', 'match.teamB'])),
            'message' => 'Time slot created successfully.',
        ], 201);
    }

    /**
     * Get a single time slot
     */
    public function show(Tournament $tournament, MatchTimeSlot $slot): JsonResponse
    {
        abort_if($slot->tournament_id !== $tournament->id, 404);

        return response()->json([
            'success' => true,
            'data' => $this->formatSlot($slot->load(['ground', 'match.teamA', 'match.teamB'])),
        ]);
    }

    /**
     * Update a time slot
     */
    public function update(Tournament $tournament, MatchTimeSlot $slot, Request $request): JsonResponse
    {
        abort_if($slot->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'ground_id' => 'nullable|exists:grounds,id',
            'slot_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'is_available' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $slot->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatSlot($slot->fresh(['ground', 'match.teamA', 'match.teamB'])),
            'message' => 'Time slot updated successfully.',
        ]);
    }

    /**
     * Delete a time slot
     */
    public function destroy(Tournament $tournament, MatchTimeSlot $slot): JsonResponse
    {
        abort_if($slot->tournament_id !== $tournament->id, 404);

        // Release match if assigned
        if ($slot->match) {
            $slot->releaseMatch();
        }

        $slot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Time slot deleted successfully.',
        ]);
    }

    /**
     * Assign a match to a time slot
     */
    public function assignMatch(Tournament $tournament, MatchTimeSlot $slot, Request $request): JsonResponse
    {
        abort_if($slot->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'match_id' => 'required|exists:matches,id',
        ]);

        $match = Matches::findOrFail($validated['match_id']);

        // Verify match belongs to same tournament
        if ($match->tournament_id !== $tournament->id) {
            return response()->json([
                'success' => false,
                'message' => 'Match does not belong to this tournament.',
            ], 422);
        }

        // Check if slot is available
        if (!$slot->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'This slot is not available.',
            ], 422);
        }

        // Assign match
        $success = $slot->assignMatch($match);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign match to slot.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSlot($slot->fresh(['ground', 'match.teamA', 'match.teamB'])),
            'message' => 'Match assigned to slot successfully.',
        ]);
    }

    /**
     * Remove match from a time slot
     */
    public function releaseMatch(Tournament $tournament, MatchTimeSlot $slot): JsonResponse
    {
        abort_if($slot->tournament_id !== $tournament->id, 404);

        if (!$slot->match) {
            return response()->json([
                'success' => false,
                'message' => 'No match is assigned to this slot.',
            ], 422);
        }

        $slot->releaseMatch();

        return response()->json([
            'success' => true,
            'data' => $this->formatSlot($slot->fresh(['ground', 'match.teamA', 'match.teamB'])),
            'message' => 'Match released from slot successfully.',
        ]);
    }

    /**
     * Bulk create time slots
     */
    public function bulkCreate(Tournament $tournament, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slots' => 'required|array|min:1',
            'slots.*.ground_id' => 'nullable|exists:grounds,id',
            'slots.*.slot_date' => 'required|date',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i',
        ]);

        $created = [];
        $skipped = 0;

        foreach ($validated['slots'] as $slotData) {
            // Check for duplicate
            $exists = MatchTimeSlot::where('tournament_id', $tournament->id)
                ->where('ground_id', $slotData['ground_id'] ?? null)
                ->whereDate('slot_date', $slotData['slot_date'])
                ->where('start_time', $slotData['start_time'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $slot = $tournament->timeSlots()->create($slotData);
            $created[] = $this->formatSlot($slot);
        }

        return response()->json([
            'success' => true,
            'data' => $created,
            'created_count' => count($created),
            'skipped_count' => $skipped,
            'message' => sprintf('Created %d slots, skipped %d duplicates.', count($created), $skipped),
        ], 201);
    }

    /**
     * Format slot for JSON response
     */
    protected function formatSlot(MatchTimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'tournament_id' => $slot->tournament_id,
            'ground' => $slot->ground ? [
                'id' => $slot->ground->id,
                'name' => $slot->ground->name,
            ] : null,
            'slot_date' => $slot->slot_date->format('Y-m-d'),
            'display_date' => $slot->display_date,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'time_range' => $slot->time_range,
            'is_available' => $slot->isAvailable(),
            'match' => $slot->match ? [
                'id' => $slot->match->id,
                'match_number' => $slot->match->match_number,
                'team_a' => $slot->match->teamA?->name ?? 'TBD',
                'team_b' => $slot->match->teamB?->name ?? 'TBD',
                'stage' => $slot->match->stage,
                'stage_display' => $slot->match->stage_display,
                'status' => $slot->match->status,
            ] : null,
            'notes' => $slot->notes,
            'created_at' => $slot->created_at->toIso8601String(),
            'updated_at' => $slot->updated_at->toIso8601String(),
        ];
    }
}
