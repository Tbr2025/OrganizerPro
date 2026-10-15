<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditFeature;
use App\Models\Organization;
use App\Models\Tournament;
use App\Services\Credits\CreditGate;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Tournament\SeasonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "Start new season" (copying what the organizer ticks) and, for Super
 * Admin, linking existing tournaments as seasons of one another.
 */
class TournamentSeasonController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly CreditGate $gate,
        private readonly CreditService $credits,
    )
    {
        $this->middleware('permission:tournament.create')->only('store');
        $this->middleware('role:Superadmin')->only('link');
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'copy' => 'array',
            'copy.*' => ['nullable', Rule::in(SeasonService::COPY)],
            'presets' => 'nullable|array',
            'presets.*' => 'integer',
        ]);
        $data['copy'] = $data['copy'] ?? [];

        $user = Auth::user();
        $org = Organization::findOrFail($tournament->organization_id);

        // Credits: a new season is a new tournament and pays the tournament fee as it is
        // created (Superadmin creates free). Not enough credits → nothing is created.
        $fee = $this->gate->fee(CreditFeature::TOURNAMENT_PUBLISH, $org, $user);
        if ($fee > $this->credits->balance($org)) {
            return back()->withInput()->with('error', 'A new season needs ' . $this->credits->format($fee)
                . '; your balance is ' . $this->credits->format($this->credits->balance($org)) . '. Top up from the Credits page.');
        }
        try {
            $season = $this->gate->createTournament($org, fn () => $this->seasons->startNextSeason($tournament, $data, $user), $user);
        } catch (InsufficientCreditsException $e) {
            return back()->withInput()->with('error', $e->getMessage() . ' Top up from the Credits page.');
        }

        return redirect()->route('admin.tournaments.dashboard', $season)
            ->with('success', "{$season->name} created as Season {$season->season_number}. Review its dates and settings, then open registration.");
    }

    public function link(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'season_of_id' => ['nullable', 'integer', Rule::exists('tournaments', 'id')->where('organization_id', $tournament->organization_id)],
            'season_number' => 'nullable|integer|min:1|max:999',
        ]);

        $seasonOf = ! empty($data['season_of_id']) && (int) $data['season_of_id'] !== $tournament->id
            ? Tournament::find($data['season_of_id'])
            : null;

        $this->seasons->link($tournament, $seasonOf, $data['season_number'] ?? null);

        return back()->with('success', $seasonOf ? 'Season link saved.' : 'Removed from its series.');
    }
}
