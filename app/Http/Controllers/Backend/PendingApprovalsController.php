<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TournamentRegistration;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PendingApprovalsController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['player.view']);

        $query = TournamentRegistration::whereNotNull('pending_changes')
            ->with(['player', 'tournament'])
            ->latest('pending_changes_submitted_at');

        // Search by player name
        if ($search = $request->get('search')) {
            $query->whereHas('player', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by tournament
        if ($tournamentId = $request->get('tournament_id')) {
            $query->where('tournament_id', $tournamentId);
        }

        $registrations = $query->paginate(25)->appends($request->query());

        $tournaments = Tournament::whereHas('registrations', function ($q) {
            $q->whereNotNull('pending_changes');
        })->orderBy('name')->get(['id', 'name']);

        $totalPending = TournamentRegistration::whereNotNull('pending_changes')->count();

        return view('backend.pages.pending-approvals.index', compact(
            'registrations', 'tournaments', 'totalPending'
        ));
    }
}
