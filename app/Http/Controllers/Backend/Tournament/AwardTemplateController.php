<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\TournamentAward;
use App\Models\Tournament;
use Illuminate\Http\Request;

class AwardTemplateController extends Controller
{
    /**
     * List all awards for a tournament
     */
    public function index(Tournament $tournament)
    {
        $awards = $tournament->awards()->orderBy('order')->get();

        return view('backend.pages.awards.index', compact('tournament', 'awards'));
    }

    /**
     * Create a new award
     */
    public function store(Tournament $tournament, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'is_match_level' => 'boolean',
        ]);

        $tournament->awards()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '🏆',
            'is_match_level' => $validated['is_match_level'] ?? true,
            'is_active' => true,
            'order' => $tournament->awards()->count() + 1,
            'template_settings' => TournamentAward::getDefaultTemplateSettings($validated['name']),
        ]);

        return redirect()
            ->route('admin.tournaments.awards.index', $tournament)
            ->with('success', 'Award created successfully.');
    }

    /**
     * Delete an award
     */
    public function destroy(TournamentAward $award)
    {
        $tournamentId = $award->tournament_id;
        $award->delete();

        return redirect()
            ->route('admin.tournaments.awards.index', $tournamentId)
            ->with('success', 'Award deleted successfully.');
    }
}
