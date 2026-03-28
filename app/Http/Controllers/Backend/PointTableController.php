<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Poster\PointTablePosterService;
use App\Services\Poster\TemplateRenderService;
use App\Services\Tournament\PointTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PointTableController extends Controller
{
    public function __construct(
        private readonly PointTableService $pointTableService,
        private readonly PointTablePosterService $posterService
    ) {}

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $groupId = $request->get('group_id');

        if ($groupId) {
            $entries = $this->pointTableService->getPointTable($tournament, $groupId);
            $selectedGroup = $tournament->groups()->find($groupId);
        } else {
            $entries = collect();
            $selectedGroup = null;
        }

        $pointTableByGroups = $this->pointTableService->getPointTableByGroups($tournament);

        return view('backend.pages.tournaments.point-table.index', [
            'tournament' => $tournament,
            'entries' => $entries,
            'selectedGroup' => $selectedGroup,
            'pointTableByGroups' => $pointTableByGroups,
            'groups' => $tournament->groups,
            'breadcrumbs' => [
                'title' => __('Point Table'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function recalculate(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            $this->pointTableService->recalculatePointTable($tournament);
            return redirect()->back()->with('success', __('Point table recalculated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to recalculate: ') . $e->getMessage());
        }
    }

    public function generatePoster(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $groupId = $request->get('group_id');

        // Check for active point_table template
        $template = $tournament->templates()
            ->where('type', TournamentTemplate::TYPE_POINT_TABLE)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        try {
            if ($groupId) {
                $group = $tournament->groups()->findOrFail($groupId);

                if ($template) {
                    $this->generateWithTemplate($template, $tournament, $group);
                } else {
                    $this->posterService->generate($group);
                }

                return redirect()->back()->with('success', __('Point table poster generated for :group.', ['group' => $group->name]));
            } else {
                if ($template) {
                    foreach ($tournament->groups as $group) {
                        $this->generateWithTemplate($template, $tournament, $group);
                    }
                    return redirect()->back()->with('success', __(':count point table posters generated.', ['count' => $tournament->groups->count()]));
                } else {
                    $paths = $this->posterService->generateAllGroups($tournament);
                    return redirect()->back()->with('success', __(':count point table posters generated.', ['count' => count($paths)]));
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate poster: ') . $e->getMessage());
        }
    }

    private function generateWithTemplate(TournamentTemplate $template, Tournament $tournament, $group): string
    {
        $entries = $group->pointTableEntries()->with('team')->ranked()->get();

        $data = [
            'tournament_name' => $tournament->name,
            'tournament_logo' => $tournament->settings?->logo ?? '',
            'group_name' => $group->name,
            'last_updated' => now()->format('M d, Y H:i'),
            'table_data' => $entries->map(fn($entry) => [
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
            ])->toArray(),
        ];

        $renderService = new TemplateRenderService();
        $filename = 'points-' . $group->id . '-' . now()->format('Y-m-d-His') . '.png';
        return $renderService->renderAndSave($template, $data, $filename);
    }

    public function initialize(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            $this->pointTableService->initializePointTable($tournament);
            return redirect()->back()->with('success', __('Point table initialized successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to initialize: ') . $e->getMessage());
        }
    }

    public function updateQualified(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'qualified_team_ids' => 'array',
            'qualified_team_ids.*' => 'exists:actual_teams,id',
        ]);

        // Reset all qualified flags
        $tournament->pointTableEntries()->update(['qualified' => false]);

        // Set qualified for selected teams
        if (!empty($validated['qualified_team_ids'])) {
            $tournament->pointTableEntries()
                ->whereIn('actual_team_id', $validated['qualified_team_ids'])
                ->update(['qualified' => true]);
        }

        return redirect()->back()->with('success', __('Qualified teams updated.'));
    }
}
