<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\TournamentAward;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AwardTemplateController extends Controller
{
    /**
     * Show template editor for a tournament award
     */
    public function edit(TournamentAward $award)
    {
        $tournament = $award->tournament;
        $settings = $award->getMergedTemplateSettings();

        return view('backend.pages.awards.template-editor', compact(
            'award',
            'tournament',
            'settings'
        ));
    }

    /**
     * Update template settings
     */
    public function update(TournamentAward $award, Request $request)
    {
        $validated = $request->validate([
            'template_settings' => 'required|array',
        ]);

        $award->update([
            'template_settings' => $validated['template_settings'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Template settings updated successfully.');
    }

    /**
     * Update template settings via AJAX
     */
    public function updateAjax(TournamentAward $award, Request $request)
    {
        $validated = $request->validate([
            'template_settings' => 'required|array',
        ]);

        $award->update([
            'template_settings' => $validated['template_settings'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template settings saved.',
        ]);
    }

    /**
     * Upload background image for template
     */
    public function uploadBackground(TournamentAward $award, Request $request)
    {
        $request->validate([
            'background_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('background_image')->store('award-templates/backgrounds', 'public');

        // Update template settings with new background
        $settings = $award->template_settings ?? [];
        $settings['background']['image'] = $path;
        $award->update(['template_settings' => $settings]);

        return response()->json([
            'success' => true,
            'image_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Upload custom icon image for template
     */
    public function uploadIcon(TournamentAward $award, Request $request)
    {
        $request->validate([
            'icon_image' => 'required|image|mimes:png,svg|max:2048',
        ]);

        $path = $request->file('icon_image')->store('award-templates/icons', 'public');

        // Update template settings with new icon
        $settings = $award->template_settings ?? [];
        $settings['award_icon']['type'] = 'image';
        $settings['award_icon']['image'] = $path;
        $award->update(['template_settings' => $settings]);

        return response()->json([
            'success' => true,
            'image_url' => asset('storage/' . $path),
            'image_path' => $path,
        ]);
    }

    /**
     * Reset template to defaults
     */
    public function resetToDefaults(TournamentAward $award)
    {
        $award->update([
            'template_settings' => TournamentAward::getDefaultTemplateSettings($award->name),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Template reset to defaults.');
    }

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

        $award = $tournament->awards()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '🏆',
            'is_match_level' => $validated['is_match_level'] ?? true,
            'is_active' => true,
            'order' => $tournament->awards()->count() + 1,
            'template_settings' => TournamentAward::getDefaultTemplateSettings($validated['name']),
        ]);

        return redirect()
            ->route('admin.awards.template.edit', $award)
            ->with('success', 'Award created. Now customize the template.');
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
