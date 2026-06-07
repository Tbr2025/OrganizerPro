<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\Charts\PostChartService;
use App\Services\Charts\UserChartService;
use App\Services\LanguageService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserChartService $userChartService,
        private readonly LanguageService $languageService,
        private readonly PostChartService $postChartService
    ) {
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('Team Manager') && !$user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('team-manager.dashboard');
        }

        $this->checkAuthorization($user, ['dashboard.view']);

        // Build org-scoped helpers for non-Superadmin users
        $isSuperadmin = $user->hasRole('Superadmin');
        $orgId = $isSuperadmin ? null : $user->organization_id;
        $orgTournamentIds = $isSuperadmin ? null : Tournament::where('organization_id', $orgId)->pluck('id');

        // Tournament statistics
        $tournamentQuery = $isSuperadmin ? Tournament::query() : Tournament::where('organization_id', $orgId);
        $tournamentStats = [
            'total' => (clone $tournamentQuery)->count(),
            'draft' => (clone $tournamentQuery)->where('status', 'draft')->count(),
            'registration' => (clone $tournamentQuery)->where('status', 'registration')->count(),
            'ongoing' => (clone $tournamentQuery)->whereIn('status', ['ongoing', 'in_progress', 'active'])->count(),
            'completed' => (clone $tournamentQuery)->where('status', 'completed')->count(),
        ];

        // Match statistics
        $matchQuery = $isSuperadmin ? Matches::query() : Matches::whereIn('tournament_id', $orgTournamentIds);
        $matchStats = [
            'total' => (clone $matchQuery)->count(),
            'upcoming' => (clone $matchQuery)->where('status', 'upcoming')->count(),
            'live' => (clone $matchQuery)->whereIn('status', ['live', 'in_progress'])->count(),
            'completed' => (clone $matchQuery)->where('status', 'completed')->count(),
        ];

        // Team and player counts
        $teamCount = $isSuperadmin ? ActualTeam::count() : ActualTeam::where('organization_id', $orgId)->count();
        $playerCount = $isSuperadmin
            ? Player::count()
            : Player::whereHas('actualTeam', fn($q) => $q->where('organization_id', $orgId))->count();

        // Recent registrations
        $registrationQuery = $isSuperadmin
            ? TournamentRegistration::query()
            : TournamentRegistration::whereIn('tournament_id', $orgTournamentIds);
        $pendingRegistrations = (clone $registrationQuery)->where('status', 'pending')->count();
        $recentRegistrations = (clone $registrationQuery)->with(['tournament', 'team', 'player'])
            ->latest()
            ->take(5)
            ->get();

        // Upcoming matches
        $upcomingMatches = (clone $matchQuery)->with(['tournament', 'teamA', 'teamB', 'ground'])
            ->where('status', 'upcoming')
            ->whereNotNull('match_date')
            ->orderBy('match_date')
            ->take(5)
            ->get();

        // Recent tournaments
        $recentTournaments = (clone $tournamentQuery)->with('settings')
            ->latest()
            ->take(5)
            ->get();

        // Live matches
        $liveMatches = (clone $matchQuery)->with(['tournament', 'teamA', 'teamB'])
            ->whereIn('status', ['live', 'in_progress'])
            ->get();

        return view(
            'backend.pages.dashboard.index',
            [
                // Tournament stats
                'tournament_stats' => $tournamentStats,
                'match_stats' => $matchStats,
                'team_count' => $teamCount,
                'player_count' => $playerCount,
                'pending_registrations' => $pendingRegistrations,
                'recent_registrations' => $recentRegistrations,
                'upcoming_matches' => $upcomingMatches,
                'recent_tournaments' => $recentTournaments,
                'live_matches' => $liveMatches,

                // Superadmin multi-tenant data
                'is_superadmin' => $isSuperadmin,
                'organizations' => $isSuperadmin ? Organization::withCount(['tournaments', 'actualTeams'])->get() : collect(),
                'org_name' => !$isSuperadmin && $orgId ? Organization::find($orgId)?->name : null,

                // Original stats
                'total_users' => number_format($isSuperadmin ? User::count() : User::where('organization_id', $orgId)->count()),
                'total_roles' => number_format(Role::count()),
                'total_permissions' => number_format(Permission::count()),
                'languages' => [
                    'total' => number_format(count($this->languageService->getLanguages())),
                    'active' => number_format(count($this->languageService->getActiveLanguages())),
                ],
                'user_growth_data' => $this->userChartService->getUserGrowthData(
                    request()->get('chart_filter_period', 'last_12_months')
                )->getData(true),
                'user_history_data' => $this->userChartService->getUserHistoryData(),
                'post_stats' => $this->postChartService->getPostActivityData(
                    request()->get('post_chart_filter_period', 'last_6_months')
                ),
                'breadcrumbs' => [
                    'title' => __('Dashboard'),
                    'show_home' => false,
                    'show_current' => false,
                ],
            ]
        );
    }
}
