<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Tournament\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {}

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $type = $request->get('type');
        $status = $request->get('status', 'pending');

        $query = $tournament->registrations()
            ->with(['player', 'processedBy', 'actualTeam'])
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $registrations = $query->paginate(20);

        return view('backend.pages.tournaments.registrations.index', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'pendingCount' => $tournament->registrations()->pending()->count(),
            'approvedCount' => $tournament->registrations()->approved()->count(),
            'rejectedCount' => $tournament->registrations()->rejected()->count(),
            'breadcrumbs' => [
                'title' => __('Registrations'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function show(Tournament $tournament, TournamentRegistration $registration): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        // Get approved registered players with linked users for captain selection
        $approvedPlayerUsers = collect();
        if ($registration->isTeamRegistration() && $registration->isPending()) {
            $approvedPlayerUsers = TournamentRegistration::where('tournament_id', $tournament->id)
                ->approved()
                ->players()
                ->whereHas('player.user')
                ->with('player.user')
                ->get()
                ->map(fn ($reg) => $reg->player->user)
                ->unique('id')
                ->values();
        }

        return view('backend.pages.tournaments.registrations.show', [
            'tournament' => $tournament,
            'registration' => $registration->load(['player', 'processedBy', 'actualTeam']),
            'approvedPlayerUsers' => $approvedPlayerUsers,
            'breadcrumbs' => [
                'title' => __('Registration Details'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                    ['label' => __('Registrations'), 'url' => route('admin.tournaments.registrations.index', $tournament)],
                ],
            ],
        ]);
    }

    public function approve(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if (!$registration->isPending()) {
            return redirect()->back()->with('error', __('This registration has already been processed.'));
        }

        if ($registration->isPlayerRegistration()) {
            $result = $this->registrationService->approvePlayerRegistration($registration, Auth::id());
        } else {
            $captainUserId = $request->input('captain_user_id') ? (int) $request->input('captain_user_id') : null;
            $result = $this->registrationService->approveTeamRegistration($registration, Auth::id(), $captainUserId);
        }

        if ($result) {
            return redirect()->back()->with('success', __('Registration approved successfully.'));
        }

        return redirect()->back()->with('error', __('Failed to approve registration.'));
    }

    public function reject(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if (!$registration->isPending()) {
            return redirect()->back()->with('error', __('This registration has already been processed.'));
        }

        $remarks = $request->input('remarks');

        $result = $this->registrationService->rejectRegistration($registration, Auth::id(), $remarks);

        if ($result) {
            return redirect()->back()->with('success', __('Registration rejected.'));
        }

        return redirect()->back()->with('error', __('Failed to reject registration.'));
    }

    public function forceDelete(Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // If approved team registration, delete the linked ActualTeam first (cascade handles pivot)
        if ($registration->isTeamRegistration() && $registration->isApproved() && $registration->actualTeam) {
            $registration->actualTeam->delete();
        }

        $registration->delete();

        return redirect()->route('admin.tournaments.registrations.index', $tournament)
            ->with('success', __('Registration deleted successfully.'));
    }

    public function bulkApprove(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $ids = $request->input('registration_ids', []);

        $approved = 0;
        foreach ($ids as $id) {
            $registration = TournamentRegistration::find($id);
            if ($registration && $registration->isPending()) {
                if ($registration->isPlayerRegistration()) {
                    $this->registrationService->approvePlayerRegistration($registration, Auth::id());
                } else {
                    $this->registrationService->approveTeamRegistration($registration, Auth::id());
                }
                $approved++;
            }
        }

        return redirect()->back()->with('success', __(':count registrations approved.', ['count' => $approved]));
    }
}
