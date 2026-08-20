<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Ground;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Poster\MatchPosterService;
use App\Services\Tournament\FixtureGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TournamentFixtureController extends Controller
{
    public function __construct(
        private readonly FixtureGeneratorService $fixtureService,
        private readonly MatchPosterService $posterService
    ) {
    }

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $stage = $request->get('stage');
        $groupId = $request->get('group_id');

        $query = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground', 'group', 'result'])
            ->orderBy('match_date')
            ->orderBy('match_number');

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($groupId) {
            $query->where('tournament_group_id', $groupId);
        }

        $matches = $query->get();

        // Group matches by stage
        $groupedMatches = $matches->groupBy('stage');

        /*
         * The same fixtures again, nested stage → pool, for the section controls.
         *
         * Enable/disable and select-all act on a SECTION, and a section is either a whole stage
         * or one pool inside it — so the view needs the nesting rather than a flat list it would
         * have to regroup in Blade. Keyed by pool id, with 0 standing for "no pool", which is
         * where knockout and playoff fixtures land: they have a stage and no group, and dropping
         * them would quietly hide half the page's fixtures from its own controls.
         */
        $sections = $groupedMatches->map(
            fn ($stageMatches) => $stageMatches->groupBy(fn ($m) => $m->tournament_group_id ?? 0)
        );

        $grounds = Ground::where(function ($q) use ($tournament) {
            $q->where('organization_id', $tournament->organization_id)
              ->orWhereNull('organization_id');
        })->active()->get();

        $teams = ActualTeam::forTournament($tournament->id)->get();

        return view('backend.pages.tournaments.fixtures.index', [
            'tournament' => $tournament,
            'matches' => $matches,
            'groupedMatches' => $groupedMatches,
            'sections' => $sections,
            'groups' => $tournament->groups,
            'grounds' => $grounds,
            'teams' => $teams,
            'breadcrumbs' => [
                'title' => __('Fixtures'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function generateGroupStage(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check prerequisites
        if ($tournament->groups()->count() === 0) {
            return redirect()->back()->with('error', __('Please create groups first.'));
        }

        $totalTeams = 0;
        foreach ($tournament->groups as $group) {
            if ($group->teams()->count() < 2) {
                return redirect()->back()->with('error', __('Each group must have at least 2 teams.'));
            }
            $totalTeams += $group->teams()->count();
        }

        if ($totalTeams === 0) {
            return redirect()->back()->with('error', __('Please add teams to groups first.'));
        }

        // Check if fixtures already exist
        if ($tournament->matches()->where('stage', 'group')->count() > 0) {
            return redirect()->back()->with('error', __('Group stage fixtures already exist. Delete them first to regenerate.'));
        }

        try {
            $fixtures = $this->fixtureService->generateGroupStageFixtures($tournament);
            return redirect()->back()->with('success', __(':count group stage matches generated.', ['count' => $fixtures->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate fixtures: ') . $e->getMessage());
        }
    }

    public function generateKnockouts(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $stage = $request->input('stage', 'semi_final');

        // Validate stage
        if (! in_array($stage, ['quarter_final', 'semi_final', 'final', 'third_place'])) {
            return redirect()->back()->with('error', __('Invalid stage.'));
        }

        // Check if this stage fixtures already exist
        if ($tournament->matches()->where('stage', $stage)->count() > 0) {
            return redirect()->back()->with('error', __(':stage fixtures already exist.', ['stage' => ucfirst(str_replace('_', ' ', $stage))]));
        }

        try {
            $fixtures = $this->fixtureService->generateKnockoutFixtures($tournament, $stage);
            return redirect()->back()->with('success', __(':count :stage matches generated.', [
                'count' => $fixtures->count(),
                'stage' => str_replace('_', ' ', $stage),
            ]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate fixtures: ') . $e->getMessage());
        }
    }

    public function reschedule(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'match_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
        ]);

        $ground = isset($validated['ground_id']) ? Ground::find($validated['ground_id']) : null;

        $this->fixtureService->rescheduleMatch(
            $match,
            Carbon::parse($validated['match_date']),
            $ground
        );

        if (isset($validated['start_time'])) {
            $match->update(['start_time' => $validated['start_time']]);
        }
        if (isset($validated['end_time'])) {
            $match->update(['end_time' => $validated['end_time']]);
        }

        // Reset poster sent status since date changed
        $match->update(['poster_sent' => false, 'poster_sent_at' => null]);

        return redirect()->back()->with('success', __('Match rescheduled successfully.'));
    }

    public function cancel(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $reason = $request->input('reason');

        $this->fixtureService->cancelMatch($match, $reason);

        return redirect()->back()->with('success', __('Match cancelled.'));
    }

    public function generatePoster(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Pre-flight checks with helpful messages
        $issues = $this->checkPosterRequirements($tournament, $match);
        if (! empty($issues)) {
            return redirect()->back()->with('error', 'Poster generation issue: ' . implode(' | ', $issues));
        }

        try {
            // Use default match poster template if one is set
            $template = $tournament->getTemplate(TournamentTemplate::TYPE_MATCH_POSTER);

            if ($template) {
                if (! $template->background_image || ! Storage::disk('public')->exists($template->background_image)) {
                    return redirect()->back()->with('error', 'Template "' . $template->name . '" has no background image. Please edit the template and upload a background image first.');
                }
                $enhancedService = new \App\Services\Poster\EnhancedMatchPosterService();
                $path = $enhancedService->generateFromTemplate($match, $template);
            } elseif ($match->isHighStakes()) {
                $path = $this->posterService->generateFinalsPosters($match);
            } else {
                $path = $this->posterService->generate($match);
            }

            return redirect()->back()->with('success', __('Match poster generated successfully.'));
        } catch (\Throwable $e) {
            \Log::error("Poster generation failed for match {$match->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            // Provide helpful error messages
            $message = $e->getMessage();
            if (str_contains($message, 'font') || str_contains($message, 'ttf')) {
                $message = 'Font files not found on server. Please upload fonts to public/fonts/ directory.';
            } elseif (str_contains($message, 'memory') || str_contains($message, 'allocat')) {
                $message = 'Server ran out of memory. Try reducing image sizes in the template.';
            } elseif (str_contains($message, 'permission') || str_contains($message, 'mkdir')) {
                $message = 'Storage directory permission error. Check storage/app/public/ permissions.';
            }

            return redirect()->back()->with('error', __('Failed to generate poster: ') . $message);
        }
    }

    /**
     * Check poster generation requirements and return list of issues
     */
    protected function checkPosterRequirements(Tournament $tournament, Matches $match): array
    {
        $issues = [];

        // Check if teams are assigned
        if (! $match->team_a_id || ! $match->team_b_id) {
            $issues[] = 'Both teams must be assigned to generate a poster (Team A or Team B is TBD).';
        }

        // Check if GD extension is available
        if (! extension_loaded('gd')) {
            $issues[] = 'PHP GD extension is not installed on the server.';
        }

        // Check if font files exist
        $fontPath = public_path('fonts/Oswald-Bold.ttf');
        if (! file_exists($fontPath)) {
            $fontPath = public_path('fonts/Montserrat-Medium.ttf');
            if (! file_exists($fontPath)) {
                $issues[] = 'No font files found in public/fonts/. Upload Oswald-Bold.ttf or Montserrat-Medium.ttf.';
            }
        }

        // Check storage directory is writable
        $storageDir = storage_path('app/public');
        if (! is_writable($storageDir)) {
            $issues[] = 'Storage directory is not writable. Run: chmod -R 775 storage/';
        }

        return $issues;
    }

    public function deleteGroupStage(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Only allow deletion if no matches have been completed
        $completedCount = $tournament->matches()
            ->where('stage', 'group')
            ->where('status', 'completed')
            ->count();

        if ($completedCount > 0) {
            return redirect()->back()->with('error', __('Cannot delete fixtures - :count matches have already been completed.', ['count' => $completedCount]));
        }

        $deleted = $tournament->matches()->where('stage', 'group')->delete();

        return redirect()->back()->with('success', __(':count group stage matches deleted.', ['count' => $deleted]));
    }

    public function bulkGeneratePosters(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $query = $tournament->matches()->where('is_cancelled', false);

        // If not forcing regeneration, only generate missing posters
        if (! $request->boolean('regenerate')) {
            $query->whereNull('poster_image');
        }

        $matches = $query->get();

        // Use default match poster template if one is set
        $template = $tournament->getTemplate(TournamentTemplate::TYPE_MATCH_POSTER);
        $enhancedService = $template ? new \App\Services\Poster\EnhancedMatchPosterService() : null;

        $generated = 0;
        $failed = 0;
        foreach ($matches as $match) {
            try {
                if ($template) {
                    $enhancedService->generateFromTemplate($match, $template);
                } elseif ($match->isHighStakes()) {
                    $this->posterService->generateFinalsPosters($match);
                } else {
                    $this->posterService->generate($match);
                }
                $generated++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::error("Failed to generate poster for match {$match->id}: " . $e->getMessage());
            }
        }

        $message = $generated . ' poster(s) generated.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' failed.';
        }
        if ($generated === 0 && $failed === 0) {
            $message = 'All matches already have posters. Use "Regenerate All" to recreate them.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Handle tournament location as venue text
        $venue = null;
        $groundId = $request->input('ground_id');
        if ($groundId && str_starts_with($groundId, 'location:')) {
            $venue = substr($groundId, 9);
            $request->merge(['ground_id' => null]);
        }

        $validated = $request->validate([
            'team_a_id' => 'required|exists:actual_teams,id',
            'team_b_id' => 'required|exists:actual_teams,id|different:team_a_id',
            'stage' => 'required|in:group,league,quarter_final,semi_final,final,third_place,qualifier_1,eliminator,qualifier_2',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
            'group_id' => 'nullable|exists:tournament_groups,id',
            'overs' => 'nullable|integer|min:1|max:50',
        ]);

        if ($venue) {
            $validated['venue'] = $venue;
        }

        try {
            $match = $this->fixtureService->createCustomMatch($tournament, $validated);
            return redirect()->back()->with('success', __('Match created successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to create match: ') . $e->getMessage());
        }
    }

    public function update(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Handle tournament location as venue text
        $venue = null;
        $groundId = $request->input('ground_id');
        if ($groundId && str_starts_with($groundId, 'location:')) {
            $venue = substr($groundId, 9);
            $request->merge(['ground_id' => null]);
        }

        $validated = $request->validate([
            'team_a_id' => 'nullable|exists:actual_teams,id',
            'team_b_id' => 'nullable|exists:actual_teams,id',
            'stage' => 'nullable|in:group,league,quarter_final,semi_final,final,third_place,qualifier_1,eliminator,qualifier_2',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
            'group_id' => 'nullable|exists:tournament_groups,id',
            'overs' => 'nullable|integer|min:1|max:50',
        ]);

        if ($venue) {
            $validated['venue'] = $venue;
            $validated['ground_id'] = null;
        }

        // Ensure team_a and team_b are different if both provided
        if (isset($validated['team_a_id']) && isset($validated['team_b_id']) && $validated['team_a_id'] === $validated['team_b_id']) {
            return redirect()->back()->with('error', __('Team A and Team B must be different.'));
        }

        try {
            $this->fixtureService->updateMatch($match, $validated);
            return redirect()->back()->with('success', __('Match updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update match: ') . $e->getMessage());
        }
    }

    public function destroy(Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if ($match->isCompleted()) {
            return redirect()->back()->with('error', __('Cannot delete a completed match.'));
        }

        try {
            $this->fixtureService->deleteMatch($match);
            return redirect()->back()->with('success', __('Match deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete match: ') . $e->getMessage());
        }
    }

    /**
     * Delete several fixtures at once — and refuse the ones that have been played.
     *
     * A played fixture is not just a row: deleting it takes its scorecard with it and silently
     * moves the points table, which nobody watching the bulk action would connect to what they
     * just did. `destroy()` already refuses a completed match one at a time; this keeps the same
     * rule and REPORTS what it kept, because a bulk action that quietly does less than asked is
     * worse than one that explains itself.
     */
    public function bulkDestroy(Tournament $tournament, Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        // Scoped to THIS tournament, not just to the ids given: an id from another tournament
        // must not be deletable by posting it here.
        $matches = $tournament->matches()
            ->whereIn('id', $validated['ids'])
            ->with('result')
            ->get();

        if ($matches->isEmpty()) {
            return redirect()->back()->with('error', __('Those fixtures are not in this tournament.'));
        }

        // Completed, or holding a result or a winner. Any of the three means somebody played it.
        [$kept, $deletable] = $matches->partition(
            fn (Matches $m) => $m->isCompleted() || $m->result !== null || $m->winner_team_id !== null
        );

        $deleted = 0;

        foreach ($deletable as $match) {
            try {
                $this->fixtureService->deleteMatch($match);
                $deleted++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = trans_choice(':count fixture deleted.|:count fixtures deleted.', $deleted, ['count' => $deleted]);

        if ($kept->isNotEmpty()) {
            // Named, not counted: "3 were kept" leaves an organizer hunting for which three.
            $names = $kept->take(5)->map(fn (Matches $m) => $m->name ?: '#' . $m->match_number)->implode(', ');
            $more = $kept->count() > 5 ? __(' and :n more', ['n' => $kept->count() - 5]) : '';

            return redirect()->back()
                ->with($deleted > 0 ? 'success' : 'error', $message)
                ->with('kept_fixtures', __(
                    ':n kept because they have results: :names:more. Delete those individually if you mean to.',
                    ['n' => $kept->count(), 'names' => $names, 'more' => $more]
                ));
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Show or hide fixtures on the public site, without deleting anything.
     *
     * A schedule is drafted before it is announced. The alternative before this was to mark a
     * fixture CANCELLED — which spectators read as "called off", a different statement entirely —
     * or to delete it and lose the work.
     *
     * Takes either an explicit list of ids, or a whole section (a stage, a pool, or a pool within
     * a stage). The section form is not sugar: it is one statement the database applies atomically,
     * where posting 28 ids can half-succeed.
     */
    public function bulkPublish(Tournament $tournament, Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'published' => 'required|boolean',
            'ids' => 'array',
            'ids.*' => 'integer',
            'stage' => 'nullable|string|max:50',
            'group_id' => 'nullable|integer',
        ]);

        $query = $tournament->matches();
        $scoped = false;

        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
            $scoped = true;
        }

        if (! empty($validated['stage'])) {
            $query->where('stage', $validated['stage']);
            $scoped = true;
        }

        if (! empty($validated['group_id'])) {
            $query->where('tournament_group_id', $validated['group_id']);
            $scoped = true;
        }

        /*
         * Refuse an unscoped call rather than treating it as "all fixtures".
         *
         * With every filter absent this would publish or hide the entire tournament, which is not
         * a thing any button on the page asks for — so it can only be a bug or a hand-made
         * request, and both deserve a refusal.
         */
        if (! $scoped) {
            return redirect()->back()->with('error', __('Nothing was selected.'));
        }

        $changed = $query->update(['is_published' => $validated['published']]);

        return redirect()->back()->with('success', $validated['published']
            ? trans_choice(':count fixture is now public.|:count fixtures are now public.', $changed, ['count' => $changed])
            : trans_choice(':count fixture hidden from the public site.|:count fixtures hidden from the public site.', $changed, ['count' => $changed]));
    }

    public function generateIplPlayoffs(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check if IPL playoff fixtures already exist
        $existingIplMatches = $tournament->matches()
            ->whereIn('stage', ['qualifier_1', 'eliminator', 'qualifier_2'])
            ->count();

        if ($existingIplMatches > 0) {
            return redirect()->back()->with('error', __('IPL playoff fixtures already exist.'));
        }

        try {
            $fixtures = $this->fixtureService->generateIplPlayoffs($tournament);
            return redirect()->back()->with('success', __(':count IPL playoff matches generated.', ['count' => $fixtures->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate IPL playoffs: ') . $e->getMessage());
        }
    }

    /**
     * Export fixtures as CSV
     */
    public function exportCsv(Tournament $tournament)
    {
        $matches = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground', 'winner', 'result'])
            ->orderBy('match_date')
            ->orderBy('start_time')
            ->get();

        $filename = str_replace(' ', '_', $tournament->name) . '_fixtures.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($matches) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['#', 'Match', 'Team A', 'Team B', 'Date', 'Time', 'Venue', 'Stage', 'Status', 'Team A Score', 'Team B Score', 'Winner', 'Result']);

            foreach ($matches as $match) {
                fputcsv($file, [
                    $match->match_number ?? $match->id,
                    'Match #' . ($match->match_number ?? $match->id),
                    $match->teamA?->name ?? 'TBD',
                    $match->teamB?->name ?? 'TBD',
                    $match->match_date?->format('d M Y') ?? '',
                    $match->start_time ? Carbon::parse($match->start_time)->format('h:i A') : '',
                    $match->ground?->name ?? $match->venue ?? '',
                    $match->stage_display ?? $match->stage ?? '',
                    ucfirst($match->status ?? 'upcoming'),
                    $match->result?->team_a_score_display ?? '',
                    $match->result?->team_b_score_display ?? '',
                    $match->winner?->name ?? '',
                    $match->result?->result_summary ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
