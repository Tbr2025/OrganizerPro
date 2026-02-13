<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Poster\WelcomeCardPosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TournamentTemplateController extends Controller
{
    /**
     * Display list of templates for a tournament
     */
    public function index(Tournament $tournament)
    {
        $templates = $tournament->templates()
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->get()
            ->groupBy('type');

        $templateTypes = TournamentTemplate::TYPES;

        return view('backend.pages.tournaments.templates.index', compact(
            'tournament',
            'templates',
            'templateTypes'
        ));
    }

    /**
     * Show create template form
     */
    public function create(Tournament $tournament, Request $request)
    {
        $type = $request->get('type', TournamentTemplate::TYPE_WELCOME_CARD);
        $placeholders = TournamentTemplate::getDefaultPlaceholders($type);

        return view('backend.pages.tournaments.templates.create', compact(
            'tournament',
            'type',
            'placeholders'
        ));
    }

    /**
     * Store a new template
     */
    public function store(Tournament $tournament, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', TournamentTemplate::TYPES),
            'background_image' => 'nullable|image|max:5120',
            'layout_json' => 'nullable|json',
            'is_default' => 'boolean',
        ]);

        // Handle file upload
        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')
                ->store('tournament_templates/' . $tournament->id, 'public');
        }

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        // Set default placeholders if not provided
        $validated['placeholders'] = TournamentTemplate::getDefaultPlaceholders($validated['type']);

        $template = $tournament->templates()->create($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return redirect()
            ->route('admin.tournament.templates.index', $tournament)
            ->with('success', 'Template created successfully.');
    }

    /**
     * Show edit template form
     */
    public function edit(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $placeholders = TournamentTemplate::getDefaultPlaceholders($template->type);

        return view('backend.pages.tournaments.templates.edit', compact(
            'tournament',
            'template',
            'placeholders'
        ));
    }

    /**
     * Update a template
     */
    public function update(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'background_image' => 'nullable|image|max:5120',
            'layout_json' => 'nullable|json',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Handle file upload
        if ($request->hasFile('background_image')) {
            // Delete old file
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }

            $validated['background_image'] = $request->file('background_image')
                ->store('tournament_templates/' . $tournament->id, 'public');
        }

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        $template->update($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return redirect()
            ->route('admin.tournament.templates.index', $tournament)
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Delete a template
     */
    public function destroy(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        // Delete file
        if ($template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }

        $template->delete();

        return redirect()
            ->route('admin.tournament.templates.index', $tournament)
            ->with('success', 'Template deleted successfully.');
    }

    /**
     * Set a template as default
     */
    public function setDefault(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $template->setAsDefault();

        return redirect()
            ->back()
            ->with('success', 'Template set as default.');
    }

    /**
     * Preview a template with sample data
     */
    public function preview(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $previewUrl = null;
        $sampleData = TournamentTemplate::getDefaultPlaceholders($template->type);

        // Convert placeholders to sample data
        $sampleData = collect($sampleData)->mapWithKeys(function ($placeholder) use ($tournament) {
            return [$placeholder => $this->getSampleValue($placeholder, $tournament)];
        })->toArray();

        // Generate preview if template has background
        if ($template->background_image) {
            $previewUrl = $template->background_image_url;
        }

        return view('backend.pages.tournaments.templates.preview', compact(
            'tournament',
            'template',
            'previewUrl',
            'sampleData'
        ));
    }

    /**
     * Get sample value for a placeholder
     */
    private function getSampleValue(string $placeholder, Tournament $tournament): string
    {
        return match ($placeholder) {
            'tournament_name' => $tournament->name,
            'tournament_logo' => $tournament->settings?->logo ? asset('storage/' . $tournament->settings->logo) : '[Logo]',
            'player_name' => 'John Doe',
            'jersey_name' => 'J. DOE',
            'jersey_number' => '10',
            'team_name' => 'Sample Team FC',
            'team_logo' => '[Team Logo]',
            'team_a_name', 'team_a_short_name' => 'Team Alpha',
            'team_b_name', 'team_b_short_name' => 'Team Beta',
            'team_a_logo', 'team_b_logo' => '[Team Logo]',
            'team_a_score' => '150/6 (20 ov)',
            'team_b_score' => '145/8 (20 ov)',
            'match_date' => now()->format('M d, Y'),
            'match_time' => '3:00 PM',
            'match_day' => now()->format('l'),
            'venue', 'ground_name' => 'City Sports Ground',
            'match_stage' => 'Group Stage',
            'match_number' => '1',
            'result_summary' => 'Team Alpha won by 5 runs',
            'winner_name' => 'Team Alpha',
            'man_of_the_match_name' => 'John Doe',
            'player_image', 'man_of_the_match_image' => '[Player Image]',
            'player_type' => 'All Rounder',
            'batting_style' => 'Right Handed',
            'bowling_style' => 'Right Arm Medium',
            'award_name' => 'Man of the Match',
            'achievement_text' => '75 runs off 45 balls',
            'description' => $tournament->description ?? 'Cricket Tournament',
            'start_date' => $tournament->start_date?->format('M d, Y') ?? 'TBA',
            'end_date' => $tournament->end_date?->format('M d, Y') ?? 'TBA',
            'location' => 'City Sports Complex',
            'registration_link' => route('public.tournament.show', $tournament->slug),
            'contact_phone' => '+1 234 567 8900',
            'contact_email' => 'info@example.com',
            'title' => 'Champions',
            'season' => 'Season 1',
            'year' => now()->year,
            'group_name' => 'Group A',
            'last_updated' => now()->format('M d, Y H:i'),
            default => "[$placeholder]",
        };
    }

    /**
     * Duplicate a template
     */
    public function duplicate(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->is_default = false;
        $newTemplate->save();

        return redirect()
            ->route('admin.tournament.templates.edit', [$tournament, $newTemplate])
            ->with('success', 'Template duplicated successfully.');
    }
}
