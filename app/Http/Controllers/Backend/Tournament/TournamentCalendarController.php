<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Ground;
use App\Models\MatchTimeSlot;
use App\Services\Tournament\CalendarFixtureService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TournamentCalendarController extends Controller
{
    protected CalendarFixtureService $calendarService;

    public function __construct(CalendarFixtureService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Display calendar view
     */
    public function index(Tournament $tournament, Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $calendarData = $this->calendarService->getCalendarData($tournament, $startDate, $endDate);
        $unscheduledMatches = $this->calendarService->getUnscheduledMatches($tournament);
        $stats = $this->calendarService->getSchedulingStats($tournament);

        $grounds = Ground::where('organization_id', $tournament->organization_id)
            ->where('is_active', true)
            ->get();

        return view('backend.pages.tournaments.calendar.index', compact(
            'tournament',
            'calendarData',
            'unscheduledMatches',
            'stats',
            'grounds',
            'month',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Generate time slots for a date range
     */
    public function generateSlots(Tournament $tournament, Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'time_slots' => 'required|array|min:1',
            'time_slots.*.start' => 'required|date_format:H:i',
            'time_slots.*.end' => 'required|date_format:H:i|after:time_slots.*.start',
            'ground_ids' => 'nullable|array',
            'ground_ids.*' => 'exists:grounds,id',
            'available_days' => 'nullable|array',
            'available_days.*' => 'integer|min:0|max:6',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $createdSlots = $this->calendarService->generateTimeSlots(
            $tournament,
            $startDate,
            $endDate,
            $validated['time_slots'],
            $validated['ground_ids'] ?? null,
            $validated['available_days'] ?? null
        );

        return redirect()
            ->back()
            ->with('success', "Created {$createdSlots->count()} time slots.");
    }

    /**
     * Auto-fill matches into available slots
     */
    public function autoFill(Tournament $tournament)
    {
        $assigned = $this->calendarService->autoFillMatches($tournament);

        $count = count($assigned);

        return redirect()
            ->back()
            ->with('success', "Auto-assigned {$count} matches to time slots.");
    }

    /**
     * Clear slots for a date range
     */
    public function clearSlots(Tournament $tournament, Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'only_available' => 'boolean',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $onlyAvailable = $request->boolean('only_available', true);

        $deleted = $this->calendarService->deleteTimeSlots($tournament, $startDate, $endDate, $onlyAvailable);

        return redirect()
            ->back()
            ->with('success', "Deleted {$deleted} time slots.");
    }

    /**
     * Get calendar data as JSON (for AJAX)
     */
    public function getCalendarJson(Tournament $tournament, Request $request)
    {
        $startDate = Carbon::parse($request->get('start', now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end', now()->endOfMonth()));

        $calendarData = $this->calendarService->getCalendarData($tournament, $startDate, $endDate);

        return response()->json($calendarData);
    }

    /**
     * Get unscheduled matches as JSON
     */
    public function getUnscheduledJson(Tournament $tournament)
    {
        $matches = $this->calendarService->getUnscheduledMatches($tournament);

        return response()->json($matches);
    }
}
