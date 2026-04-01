<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\ImageBackgroundRemovalService;
use App\Services\Poster\TemplateRenderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TournamentTemplateController extends Controller
{
    /**
     * Display list of templates for a tournament
     */
    public function index(Tournament $tournament, Request $request)
    {
        // Handle AJAX request for templates by type
        if ($request->ajax() || $request->has('ajax')) {
            $type = $request->get('type');
            $templates = $tournament->templates()
                ->when($type, fn($q) => $q->where('type', $type))
                ->orderByDesc('is_default')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'type' => $t->type,
                    'is_default' => $t->is_default,
                    'background_image_url' => $t->background_image_url,
                ]);

            return response()->json(['templates' => $templates]);
        }

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
     * Show generate poster page with data selection
     */
    public function generate(Tournament $tournament, Request $request)
    {
        $type = $request->get('type', TournamentTemplate::TYPE_MATCH_POSTER);

        // Load templates for selected type
        $templates = $tournament->templates()
            ->where('type', $type)
            ->orderByDesc('is_default')
            ->get();

        // Load matches with team captain information
        $matches = $tournament->matches()
            ->with([
                'teamA.users' => function ($query) {
                    $query->wherePivot('role', 'captain');
                },
                'teamB.users' => function ($query) {
                    $query->wherePivot('role', 'captain');
                },
                'ground',
                'winner',
                'result'
            ])
            ->orderBy('match_date')
            ->get();

        // Load players belonging to tournament's actual teams
        $players = Player::whereIn('actual_team_id', $tournament->actualTeams()->pluck('id'))
            ->with(['actualTeam', 'playerType', 'battingProfile', 'bowlingProfile'])
            ->where('status', 'approved')
            ->get();

        // Load groups for point table type
        $groups = $tournament->groups;

        return view('backend.pages.tournaments.templates.generate', compact(
            'tournament',
            'type',
            'templates',
            'matches',
            'players',
            'groups'
        ));
    }

    /**
     * Generate poster preview with actual data (AJAX)
     */
    public function generatePreview(Tournament $tournament, Request $request)
    {
        $tempFiles = [];
        try {
            $templateId = $request->input('template_id');
            $template = TournamentTemplate::findOrFail($templateId);

            abort_if($template->tournament_id !== $tournament->id, 404);

            $renderService = new TemplateRenderService();

            // Build data from request
            $data = $request->only([
                'player_name', 'jersey_number', 'team_name', 'player_image', 'player_type',
                'batting_style', 'bowling_style', 'team_logo',
                'team_a_name', 'team_b_name', 'team_a_short_name', 'team_b_short_name',
                'team_a_logo', 'team_b_logo',
                'team_a_captain_image', 'team_b_captain_image',
                'team_a_captain_name', 'team_b_captain_name',
                'team_a_score', 'team_b_score', 'winner_name', 'winner_logo',
                'team_a_score_wickets', 'team_b_score_wickets',
                'team_a_runs', 'team_b_runs', 'team_a_wickets', 'team_b_wickets',
                'team_a_overs', 'team_b_overs', 'win_margin', 'toss_result',
                'match_date', 'match_time', 'venue', 'ground_name', 'match_stage', 'match_number',
                'award_name', 'achievement_text', 'match_details',
                'man_of_the_match_name', 'man_of_the_match_image',
                'result_summary', 'batting_figures', 'bowling_figures'
            ]);

            // Add tournament info
            $data['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $data['tournament_logo'] = $tournament->settings->logo;
            }

            // For match-based types, fetch actual data from DB (avoids ConvertEmptyStringsToNull losing values)
            if ($request->input('match_id') && in_array($template->type, [
                TournamentTemplate::TYPE_MATCH_POSTER,
                TournamentTemplate::TYPE_MATCH_SUMMARY,
            ])) {
                $match = Matches::with(['teamA', 'teamB', 'winner', 'result', 'ground'])->find($request->input('match_id'));
                if ($match) {
                    $matchData = [
                        'team_a_name' => $match->teamA?->name,
                        'team_b_name' => $match->teamB?->name,
                        'team_a_short_name' => $match->teamA?->short_name ?? $match->teamA?->name,
                        'team_b_short_name' => $match->teamB?->short_name ?? $match->teamB?->name,
                        'team_a_logo' => $match->teamA?->team_logo,
                        'team_b_logo' => $match->teamB?->team_logo,
                        'match_date' => $match->match_date?->format('M d, Y'),
                        'match_time' => $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : null,
                        'venue' => $match->ground?->name ?? $match->venue,
                        'ground_name' => $match->ground?->name ?? $match->venue,
                        'match_number' => (string) ($match->match_number ?? $match->id),
                        'match_stage' => $match->stage_display,
                    ];

                    if ($match->result) {
                        $r = $match->result;
                        $matchData['team_a_score'] = $r->team_a_score_display;
                        $matchData['team_b_score'] = $r->team_b_score_display;
                        $matchData['team_a_score_wickets'] = $r->team_a_score . '/' . $r->team_a_wickets;
                        $matchData['team_b_score_wickets'] = $r->team_b_score . '/' . $r->team_b_wickets;
                        $matchData['team_a_runs'] = (string) $r->team_a_score;
                        $matchData['team_b_runs'] = (string) $r->team_b_score;
                        $matchData['team_a_wickets'] = (string) $r->team_a_wickets;
                        $matchData['team_b_wickets'] = (string) $r->team_b_wickets;
                        $matchData['team_a_overs'] = (string) $r->team_a_overs;
                        $matchData['team_b_overs'] = (string) $r->team_b_overs;
                        $matchData['result_summary'] = $r->result_summary ?: $r->generateResultSummary();
                        $matchData['win_margin'] = $r->margin ? 'Won by ' . $r->margin . ' ' . $r->result_type : '';
                        // Toss
                        if ($r->toss_won_by) {
                            $tossWinner = $r->toss_won_by == $match->team_a_id ? $match->teamA?->name : $match->teamB?->name;
                            $matchData['toss_result'] = $tossWinner . ' won toss, chose to ' . ($r->toss_decision ?? 'bat');
                        }
                    }

                    if ($match->winner) {
                        $matchData['winner_name'] = $match->winner->name;
                        $matchData['winner_logo'] = $match->winner->team_logo;
                    }

                    // DB data fills in any null/empty values from the request
                    foreach ($matchData as $key => $value) {
                        if ($value !== null && empty($data[$key])) {
                            $data[$key] = $value;
                        }
                    }
                }
            }

            // Award poster: handle uploaded player image and set defaults
            if ($template->type === TournamentTemplate::TYPE_AWARD_POSTER) {
                // Handle custom player image upload
                if ($request->hasFile('player_image_file')) {
                    $uploadedPath = $request->file('player_image_file')
                        ->store('temp_previews', 'public');
                    $data['player_image'] = $uploadedPath;
                    $tempFiles[] = $uploadedPath;
                }

                if (empty($data['player_name'])) {
                    $data['player_name'] = 'Player Name';
                }
                if (empty($data['award_name'])) {
                    $data['award_name'] = 'Award';
                }
                // Default player image if not provided
                if (empty($data['player_image'])) {
                    $data['player_image'] = 'defaults/default-player.png';
                }
            }

            // Handle point_table type — build table_data from group entries
            if ($template->type === TournamentTemplate::TYPE_POINT_TABLE && $request->input('group_id')) {
                $group = $tournament->groups()->find($request->input('group_id'));
                if ($group) {
                    $entries = $group->pointTableEntries()->with('team')->ranked()->get();
                    $data['table_data'] = $entries->map(fn($entry) => [
                        'position' => $entry->position,
                        'team_name' => $entry->team?->name ?? 'Unknown',
                        'team_logo' => $entry->team?->team_logo ?? '',
                        'matches_played' => $entry->matches_played,
                        'won' => $entry->won,
                        'lost' => $entry->lost,
                        'tied' => $entry->tied,
                        'net_run_rate' => $entry->net_run_rate,
                        'points' => $entry->points,
                        'qualified' => $entry->qualified,
                    ])->toArray();
                    $data['group_name'] = $group->name;
                    $data['last_updated'] = now()->format('M d, Y H:i');
                }
            }

            // Filter empty values (but keep table_data array)
            $data = array_filter($data, fn($v) => is_array($v) ? !empty($v) : ($v !== null && $v !== ''));

            // Render to base64
            $base64Image = $renderService->renderToBase64($template, $data);

            // Clean up temp uploaded files
            foreach ($tempFiles as $tempPath) {
                Storage::disk('public')->delete($tempPath);
            }

            return response()->json([
                'success' => true,
                'image' => $base64Image,
            ]);
        } catch (\Exception $e) {
            // Clean up temp files on error too
            foreach ($tempFiles as $tempPath) {
                Storage::disk('public')->delete($tempPath);
            }
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get match awards for award poster generation (AJAX)
     */
    public function getMatchAwards(Tournament $tournament, Matches $match)
    {
        $match->load(['teamA', 'teamB', 'result']);

        // Get awards for this match
        $awards = $match->matchAwards()
            ->with(['player.actualTeam', 'tournamentAward'])
            ->get()
            ->map(fn($award) => [
                'id' => $award->id,
                'award_name' => $award->tournamentAward->name ?? 'Award',
                'player_id' => $award->player_id,
                'player_name' => $award->player->jersey_name ?? $award->player->name ?? 'Unknown',
                'player_image' => $award->player->image_path ?? null,
                'team_name' => $award->player->actualTeam?->name ?? '',
                'team_logo' => $award->player->actualTeam?->team_logo ?? null,
            ]);

        // Build set of player IDs that have awards
        $awardPlayerIds = $awards->pluck('player_id')->filter()->toArray();

        // Get all players from both teams
        $teamAPlayers = Player::where('actual_team_id', $match->team_a_id)
            ->where('status', 'approved')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->jersey_name ?: $p->name ?: 'Player #' . $p->id,
                'image' => $p->image_path ?? null,
                'team_name' => $match->teamA?->name ?? '',
                'team_logo' => $match->teamA?->team_logo ?? null,
                'has_award' => in_array($p->id, $awardPlayerIds),
            ]);

        $teamBPlayers = Player::where('actual_team_id', $match->team_b_id)
            ->where('status', 'approved')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->jersey_name ?: $p->name ?: 'Player #' . $p->id,
                'image' => $p->image_path ?? null,
                'team_name' => $match->teamB?->name ?? '',
                'team_logo' => $match->teamB?->team_logo ?? null,
                'has_award' => in_array($p->id, $awardPlayerIds),
            ]);

        $matchData = [
            'team_a_name' => $match->teamA?->name ?? '',
            'team_a_logo' => $match->teamA?->team_logo_url ?? '',
            'team_b_name' => $match->teamB?->name ?? '',
            'team_b_logo' => $match->teamB?->team_logo_url ?? '',
            'status' => $match->status,
        ];

        // Include score data if match result exists
        if ($match->result) {
            $matchData['team_a_score'] = $match->result->team_a_score_display;
            $matchData['team_b_score'] = $match->result->team_b_score_display;
            $matchData['result_summary'] = $match->result->result_summary ?: $match->result->generateResultSummary();
        }

        return response()->json([
            'awards' => $awards,
            'players' => [
                'team_a' => $teamAPlayers,
                'team_b' => $teamBPlayers,
            ],
            'match' => $matchData,
        ]);
    }

    /**
     * Show create template form
     */
    public function create(Tournament $tournament, Request $request)
    {
        $type = $request->get('type', TournamentTemplate::TYPE_WELCOME_CARD);
        $placeholders = TournamentTemplate::getDefaultPlaceholders($type);
        $template = null; // No existing template for create

        // Use the new Fabric.js editor
        return view('backend.pages.tournaments.templates.editor', compact(
            'tournament',
            'type',
            'template',
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
            'background_image_base64' => 'nullable|string',
            'layout_json' => 'nullable|json',
            'overlay_images' => 'nullable|json',
            'canvas_width' => 'nullable|integer|min:540|max:2160',
            'canvas_height' => 'nullable|integer|min:540|max:3840',
            'is_default' => 'boolean',
        ]);

        // Handle file upload
        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')
                ->store('tournament_templates/' . $tournament->id, 'public');
        }
        // Handle base64 background image from editor
        elseif ($request->filled('background_image_base64')) {
            $validated['background_image'] = $this->saveBase64Image(
                $request->input('background_image_base64'),
                $tournament->id
            );
        }
        unset($validated['background_image_base64']);

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        // Parse overlay images JSON
        if (isset($validated['overlay_images'])) {
            $validated['overlay_images'] = json_decode($validated['overlay_images'], true);
        }

        // Set default placeholders if not provided
        $validated['placeholders'] = TournamentTemplate::getDefaultPlaceholders($validated['type']);

        $template = $tournament->templates()->create($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return redirect()
            ->route('admin.tournaments.templates.index', $tournament)
            ->with('success', 'Template created successfully.');
    }

    /**
     * Show edit template form
     */
    public function edit(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $type = $template->type;
        $placeholders = TournamentTemplate::getDefaultPlaceholders($template->type);

        // Use the new Fabric.js editor
        return view('backend.pages.tournaments.templates.editor', compact(
            'tournament',
            'template',
            'type',
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
            'background_image_base64' => 'nullable|string',
            'layout_json' => 'nullable|json',
            'overlay_images' => 'nullable|json',
            'canvas_width' => 'nullable|integer|min:540|max:2160',
            'canvas_height' => 'nullable|integer|min:540|max:3840',
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
        // Handle base64 background image from editor
        elseif ($request->filled('background_image_base64')) {
            // Delete old file
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }

            $validated['background_image'] = $this->saveBase64Image(
                $request->input('background_image_base64'),
                $tournament->id
            );
        }
        unset($validated['background_image_base64']);

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        // Parse overlay images JSON
        if (isset($validated['overlay_images'])) {
            $validated['overlay_images'] = json_decode($validated['overlay_images'], true);
        }

        $template->update($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return redirect()
            ->route('admin.tournaments.templates.index', $tournament)
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
            ->route('admin.tournaments.templates.index', $tournament)
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
    public function preview(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $previewUrl = null;
        $previewError = null;

        // Get all placeholders for this template type
        $placeholders = TournamentTemplate::getDefaultPlaceholders($template->type);
        $imagePlaceholders = ['player_image', 'team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo',
                              'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image',
                              'team_a_sponsor_logo', 'team_b_sponsor_logo', 'qr_code'];

        // Get custom data from request (text fields)
        $renderService = new TemplateRenderService();
        $textPlaceholders = array_diff($placeholders, $imagePlaceholders);
        $customData = $request->only($textPlaceholders);

        // Handle uploaded images
        $uploadedImages = [];
        $bgRemovalService = new ImageBackgroundRemovalService();

        foreach ($imagePlaceholders as $imageField) {
            if ($request->hasFile($imageField)) {
                $file = $request->file($imageField);
                // Store temporarily for preview
                $path = $file->store('temp_previews', 'public');
                $uploadedImages[] = $path;

                // Remove background for player images
                if (in_array($imageField, ['player_image', 'man_of_the_match_image', 'team_a_captain_image', 'team_b_captain_image'])) {
                    $noBgPath = $bgRemovalService->removeBackground($path);
                    if ($noBgPath) {
                        $uploadedImages[] = $noBgPath;
                        $path = $noBgPath;
                    }
                }

                $customData[$imageField] = $path;
            }
        }

        $sampleData = $renderService->getSampleData($template->type, array_filter($customData));

        // Add tournament-specific data
        $sampleData['tournament_name'] = $tournament->name;
        if ($tournament->settings?->logo) {
            $sampleData['tournament_logo'] = $tournament->settings->logo;
        }

        // Generate rendered preview if template has layout
        if ($template->background_image && !empty($template->layout_json)) {
            try {
                $previewUrl = $renderService->renderToBase64($template, $sampleData);
            } catch (\Exception $e) {
                $previewError = 'Failed to render preview: ' . $e->getMessage();
                // Fallback to just background image
                $previewUrl = $template->background_image_url;
            }
        } elseif ($template->background_image) {
            // No layout, just show background
            $previewUrl = $template->background_image_url;
        }

        // Clean up temporary uploaded images
        foreach ($uploadedImages as $path) {
            Storage::disk('public')->delete($path);
        }

        return view('backend.pages.tournaments.templates.preview', compact(
            'tournament',
            'template',
            'previewUrl',
            'sampleData',
            'previewError'
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
            'team_a_score' => '150/6 (20.0)',
            'team_b_score' => '145/8 (20.0)',
            'team_a_score_wickets' => '150/6',
            'team_b_score_wickets' => '145/8',
            'team_a_runs' => '150',
            'team_b_runs' => '145',
            'team_a_wickets' => '6',
            'team_b_wickets' => '8',
            'team_a_overs' => '20.0',
            'team_b_overs' => '20.0',
            'match_date' => now()->format('M d, Y'),
            'match_time' => '3:00 PM',
            'match_day' => now()->format('l'),
            'venue', 'ground_name' => 'City Sports Ground',
            'match_stage' => 'Group Stage',
            'match_number' => '1',
            'result_summary' => 'Team Alpha won by 5 runs',
            'winner_name' => 'Team Alpha',
            'win_margin' => 'Won by 5 runs',
            'toss_result' => 'Team Alpha won toss, chose to bat',
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
            ->route('admin.tournaments.templates.edit', [$tournament, $newTemplate])
            ->with('success', 'Template duplicated successfully.');
    }

    /**
     * Render template preview with sample data (AJAX)
     */
    public function renderPreview(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        try {
            $renderService = new TemplateRenderService();

            // Get custom data from request or use defaults
            $customData = $request->input('data', []);
            $sampleData = $renderService->getSampleData($template->type, $customData);

            // Add tournament-specific data
            $sampleData['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $sampleData['tournament_logo'] = $tournament->settings->logo;
            }

            // Render to base64
            $base64Image = $renderService->renderToBase64($template, $sampleData);

            return response()->json([
                'success' => true,
                'image' => $base64Image,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to render template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download rendered template
     */
    public function download(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        try {
            $renderService = new TemplateRenderService();

            // Get custom data from request (supports both query params and nested 'data' array)
            $customData = $request->input('data', []);
            if (empty($customData)) {
                $customData = $request->only([
                    'player_name', 'jersey_name', 'jersey_number', 'team_name',
                    'team_a_name', 'team_b_name', 'team_a_score', 'team_b_score',
                    'match_date', 'match_time', 'venue', 'match_stage', 'result_summary',
                    'winner_name', 'man_of_the_match_name'
                ]);
            }
            $sampleData = $renderService->getSampleData($template->type, array_filter($customData));

            // Add tournament-specific data
            $sampleData['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $sampleData['tournament_logo'] = $tournament->settings->logo;
            }

            // Render and save
            $filename = 'template-' . $template->id . '-' . now()->format('Y-m-d-His') . '.png';
            $path = $renderService->renderAndSave($template, $sampleData, $filename);

            $fullPath = Storage::disk('public')->path($path);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'image/png',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate template: ' . $e->getMessage());
        }
    }

    /**
     * Upload an overlay image for template
     */
    public function uploadOverlay(Tournament $tournament, Request $request)
    {
        $request->validate([
            'overlay_image' => 'required|file|mimes:png,jpg,jpeg,gif,svg,webp|max:5120',
        ]);

        $path = $request->file('overlay_image')
            ->store('tournament_templates/' . $tournament->id . '/overlays', 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Delete an overlay image
     */
    public function deleteOverlay(Tournament $tournament, Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        // Security check: ensure the path belongs to this tournament
        if (!str_contains($path, 'tournament_templates/' . $tournament->id)) {
            return response()->json(['success' => false, 'error' => 'Invalid path'], 403);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Save a base64 encoded image to storage
     */
    private function saveBase64Image(string $base64Data, int $tournamentId): string
    {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $extension = 'png';
        }

        // Decode the base64 data
        $imageData = base64_decode($base64Data);

        // Generate unique filename
        $filename = 'bg_' . time() . '_' . uniqid() . '.' . $extension;
        $path = 'tournament_templates/' . $tournamentId . '/' . $filename;

        // Save to storage
        Storage::disk('public')->put($path, $imageData);

        return $path;
    }
}
