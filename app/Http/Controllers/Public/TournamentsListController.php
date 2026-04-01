<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\View\View;

class TournamentsListController extends Controller
{
    /**
     * Show public list of all tournaments
     */
    public function index(): View
    {
        $tournaments = Tournament::with(['settings'])
            ->whereIn('status', ['registration', 'ongoing', 'completed'])
            ->orderByRaw("FIELD(status, 'registration', 'ongoing', 'completed')")
            ->orderByDesc('start_date')
            ->get();

        // Group by status (check if registration is actually open, not just DB status)
        $registrationOpen = $tournaments->filter(fn($t) => $t->status === 'registration' && ($t->settings?->isRegistrationOpen() ?? false));
        $ongoing = $tournaments->filter(fn($t) => $t->status === 'ongoing' || ($t->status === 'registration' && !($t->settings?->isRegistrationOpen() ?? false)));
        $completed = $tournaments->filter(fn($t) => $t->status === 'completed');

        return view('public.tournaments.index', [
            'registrationOpen' => $registrationOpen,
            'ongoing' => $ongoing,
            'completed' => $completed,
        ]);
    }
}
