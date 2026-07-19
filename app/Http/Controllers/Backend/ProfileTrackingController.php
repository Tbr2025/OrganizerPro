<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeLog;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileTrackingController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAuthorization(Auth::user(), ['player.view']);

        $query = ProfileChangeLog::with(['player', 'tournament', 'changedBy', 'registration'])
            ->latest();

        // Filter by action
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

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

        // Date range
        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->appends($request->query());

        // Stats
        $submittedCount = ProfileChangeLog::submitted()->count();
        $approvedCount = ProfileChangeLog::approved()->count();
        $rejectedCount = ProfileChangeLog::rejected()->count();
        $totalCount = ProfileChangeLog::count();

        $tournaments = Tournament::orderBy('name')->get(['id', 'name']);

        return view('backend.pages.profile-tracking.index', compact(
            'logs', 'tournaments',
            'submittedCount', 'approvedCount', 'rejectedCount', 'totalCount'
        ));
    }
}
