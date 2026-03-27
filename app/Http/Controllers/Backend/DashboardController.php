<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Matches;
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

        if ($user->hasRole('Team Manager') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Organizer'])) {
            return redirect()->route('team-manager.dashboard');
        }

        $this->checkAuthorization($user, ['dashboard.view']);

        // Tournament statistics
        $tournamentStats = [
            'total' => Tournament::count(),
            'draft' => Tournament::where('status', 'draft')->count(),
            'registration' => Tournament::where('status', 'registration')->count(),
            'ongoing' => Tournament::whereIn('status', ['ongoing', 'in_progress', 'active'])->count(),
            'completed' => Tournament::where('status', 'completed')->count(),
        ];

        // Match statistics
        $matchStats = [
            'total' => Matches::count(),
            'upcoming' => Matches::where('status', 'upcoming')->count(),
            'live' => Matches::whereIn('status', ['live', 'in_progress'])->count(),
            'completed' => Matches::where('status', 'completed')->count(),
        ];

        // Team and player counts
        $teamCount = ActualTeam::count();
        $playerCount = Player::count();

        // Recent registrations
        $pendingRegistrations = TournamentRegistration::where('status', 'pending')->count();
        $recentRegistrations = TournamentRegistration::with(['tournament', 'team', 'player'])
            ->latest()
            ->take(5)
            ->get();

        // Upcoming matches
        $upcomingMatches = Matches::with(['tournament', 'teamA', 'teamB', 'ground'])
            ->where('status', 'upcoming')
            ->whereNotNull('match_date')
            ->orderBy('match_date')
            ->take(5)
            ->get();

        // Recent tournaments
        $recentTournaments = Tournament::with('settings')
            ->latest()
            ->take(5)
            ->get();

        // Live matches
        $liveMatches = Matches::with(['tournament', 'teamA', 'teamB'])
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

                // Original stats
                'total_users' => number_format(User::count()),
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
