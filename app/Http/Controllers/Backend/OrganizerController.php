<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\OrganizerAssignmentMail;
use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizerController extends Controller
{
    /** Only Admin/Superadmin may manage organizers (prevent privilege escalation). */
    protected function denyOrganizers(): void
    {
        $user = Auth::user();
        abort_if($user->hasRole('Organizer') && ! $user->hasRole('Admin') && ! $user->hasRole('Superadmin'), 403);
    }

    public function index(): View
    {
        $this->denyOrganizers();
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $organizers = User::role('Organizer')
            ->when(! Auth::user()->hasRole('Superadmin'), fn ($q) => $q->where('organization_id', Auth::user()->organization_id))
            ->withCount(['assignedTournaments', 'assignedTeams', 'assignedMatches'])
            ->orderBy('name')->paginate(20);

        return view('backend.pages.organizers.index', [
            'organizers' => $organizers,
            'breadcrumbs' => ['title' => __('Organizers'), 'items' => [['label' => __('Dashboard'), 'url' => route('admin.dashboard')]]],
        ]);
    }

    public function create(): View
    {
        return $this->form(new User());
    }

    public function edit(User $organizer): View
    {
        $this->authorizeSameOrg($organizer);

        return $this->form($organizer);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->denyOrganizers();
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $mode = $request->input('user_mode', 'existing'); // existing | new
        $plainPassword = null;

        if ($mode === 'new') {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
            ]);
            $plainPassword = Str::random(12);
            $organizer = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => Str::slug($data['name']) . '-' . Str::random(5),
                'password' => Hash::make($plainPassword),
                'organization_id' => Auth::user()->organization_id,
            ]);
        } else {
            $request->validate(['user_id' => 'required|exists:users,id']);
            $organizer = User::findOrFail($request->input('user_id'));
        }

        if (! $organizer->hasRole('Organizer')) {
            $organizer->assignRole('Organizer');
        }

        $this->syncAssignments($organizer, $request);
        $this->notify($organizer, $plainPassword ? 'assigned' : 'assigned', $plainPassword);

        return redirect()->route('admin.organizers.index')->with('success', __('Organizer saved and notified.'));
    }

    public function update(Request $request, User $organizer): RedirectResponse
    {
        $this->authorizeSameOrg($organizer);

        if (! $organizer->hasRole('Organizer')) {
            $organizer->assignRole('Organizer');
        }
        $this->syncAssignments($organizer, $request);
        $this->notify($organizer, 'updated');

        return redirect()->route('admin.organizers.index')->with('success', __('Assignments updated and organizer notified.'));
    }

    public function destroy(User $organizer): RedirectResponse
    {
        $this->authorizeSameOrg($organizer);

        $organizer->assignedTournaments()->detach();
        $organizer->assignedTeams()->detach();
        $organizer->assignedMatches()->detach();
        $this->notify($organizer, 'removed');

        return redirect()->route('admin.organizers.index')->with('success', __('All assignments removed; organizer notified.'));
    }

    /** Shared create/edit form with the eligible + assigned items. */
    protected function form(User $organizer): View
    {
        $this->denyOrganizers();
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $tournaments = Tournament::forUser(Auth::user())->orderBy('name')->get();
        $tournamentIds = $tournaments->pluck('id');
        $teams = ActualTeam::whereIn('tournament_id', $tournamentIds)->orderBy('name')->get();
        $matches = Matches::whereIn('tournament_id', $tournamentIds)->orderByDesc('id')->get();

        $existingOrganizers = User::role('Organizer')
            ->when(! Auth::user()->hasRole('Superadmin'), fn ($q) => $q->where('organization_id', Auth::user()->organization_id))
            ->orderBy('name')->get();

        return view('backend.pages.organizers.form', [
            'organizer' => $organizer,
            'tournaments' => $tournaments,
            'teams' => $teams,
            'matches' => $matches,
            'existingOrganizers' => $existingOrganizers,
            'assignedTournamentIds' => $organizer->exists ? $organizer->assignedTournaments()->pluck('assignable_id')->all() : [],
            'assignedTeamIds' => $organizer->exists ? $organizer->assignedTeams()->pluck('assignable_id')->all() : [],
            'assignedMatchIds' => $organizer->exists ? $organizer->assignedMatches()->pluck('assignable_id')->all() : [],
            'breadcrumbs' => ['title' => $organizer->exists ? __('Edit Organizer') : __('New Organizer'), 'items' => [['label' => __('Organizers'), 'url' => route('admin.organizers.index')]]],
        ]);
    }

    /** Sync the three assignment types from the request (validated against eligible scope). */
    protected function syncAssignments(User $organizer, Request $request): void
    {
        $tournaments = Tournament::forUser(Auth::user())->pluck('id');
        $teamScope = ActualTeam::whereIn('tournament_id', $tournaments)->pluck('id');
        $matchScope = Matches::whereIn('tournament_id', $tournaments)->pluck('id');

        $organizer->assignedTournaments()->sync($tournaments->intersect(array_map('intval', (array) $request->input('tournaments', [])))->values()->all());
        $organizer->assignedTeams()->sync($teamScope->intersect(array_map('intval', (array) $request->input('teams', [])))->values()->all());
        $organizer->assignedMatches()->sync($matchScope->intersect(array_map('intval', (array) $request->input('matches', [])))->values()->all());
    }

    /** Email the organizer their current assignment summary. */
    protected function notify(User $organizer, string $mode, ?string $password = null): void
    {
        try {
            Mail::to($organizer->email)->send(new OrganizerAssignmentMail(
                $organizer,
                $mode,
                $organizer->assignedTournaments()->pluck('name')->all(),
                $organizer->assignedTeams()->pluck('name')->all(),
                $organizer->assignedMatches()->pluck('name')->all(),
                $password,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to email organizer assignment: ' . $e->getMessage());
        }
    }

    protected function authorizeSameOrg(User $organizer): void
    {
        $this->denyOrganizers();
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);
        if (! Auth::user()->hasRole('Superadmin')) {
            abort_if($organizer->organization_id !== Auth::user()->organization_id, 403);
        }
    }
}
