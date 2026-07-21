<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TournamentRegistration;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestedChangesController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['player.view']);

        // Registrations where admin reviewed (verified_fields is set), status is still pending,
        // and not all fields are verified — meaning correction was requested.
        $query = TournamentRegistration::where('status', 'pending')
            ->whereNotNull('verified_fields')
            ->where('type', 'player')
            ->with(['player.user', 'player.playerType', 'tournament'])
            ->latest('updated_at');

        if ($search = $request->get('search')) {
            $query->whereHas('player', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($tournamentId = $request->get('tournament_id')) {
            $query->where('tournament_id', $tournamentId);
        }

        $registrations = $query->paginate(25)->appends($request->query());

        $tournaments = Tournament::whereHas('registrations', function ($q) {
            $q->where('status', 'pending')
              ->whereNotNull('verified_fields')
              ->where('type', 'player');
        })->orderBy('name')->get(['id', 'name']);

        $totalRequested = TournamentRegistration::where('status', 'pending')
            ->whereNotNull('verified_fields')
            ->where('type', 'player')
            ->count();

        return view('backend.pages.requested-changes.index', compact(
            'registrations', 'tournaments', 'totalRequested'
        ));
    }
}
