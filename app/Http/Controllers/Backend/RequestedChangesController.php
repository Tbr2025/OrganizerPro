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
            ->with(['player.user', 'player.playerType', 'player.battingProfile', 'player.bowlingProfile', 'tournament'])
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

        // Summary stats: total reviewed, fully verified, still pending
        $allReviewed = TournamentRegistration::whereNotNull('verified_fields')
            ->where('type', 'player')
            ->with(['tournament.settings', 'tournament.customFields', 'player'])
            ->get();

        $totalReviewed = $allReviewed->count();
        $totalApproved = $allReviewed->where('status', 'approved')->count();
        $totalFullyVerified = 0;
        $totalPartial = 0;

        foreach ($allReviewed->where('status', 'pending') as $r) {
            $vFields = (array) ($r->verified_fields ?? []);
            $settings = $r->tournament?->settings;
            $rLayout = \App\Helpers\PlayerFormConfig::getFormLayout($settings, false);
            $rCustom = $r->tournament?->customFields?->where('form', 'player')->where('visible', true) ?? collect();
            $rSkip = ['name', 'image', 'terms_and_conditions'];
            $rTotal = 0; $rDone = 0;
            if ($r->player?->image_path) { $rTotal++; if (in_array('image', $vFields, true)) $rDone++; }
            foreach ($rLayout as $sec) {
                foreach ($sec['fields'] as $fk) {
                    if (in_array($fk, $rSkip)) continue;
                    $rTotal++;
                    if (in_array($fk, $vFields, true)) $rDone++;
                }
                foreach (($rCustom->where('section', $sec['key']) ?? collect()) as $scf) {
                    $rTotal++;
                    if (in_array('cf_' . $scf->id, $vFields, true)) $rDone++;
                }
            }
            if ($rTotal > 0 && $rDone === $rTotal) {
                $totalFullyVerified++;
            } else {
                $totalPartial++;
            }
        }

        return view('backend.pages.requested-changes.index', compact(
            'registrations', 'tournaments', 'totalRequested',
            'totalReviewed', 'totalApproved', 'totalFullyVerified', 'totalPartial'
        ));
    }
}
