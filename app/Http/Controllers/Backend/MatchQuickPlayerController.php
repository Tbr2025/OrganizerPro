<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Player;
use App\Services\Scoring\QuickPlayerService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * "+ Add player" on the scoring screen: a walk-in player by name and phone,
 * without leaving the match. See QuickPlayerService.
 */
class MatchQuickPlayerController extends Controller
{
    public function __construct(private readonly QuickPlayerService $quickPlayers)
    {
        $this->middleware('permission:scoring.quick_add_player');
        $this->middleware('credits.unlock:' . \App\Models\Credits\CreditFeature::SCORING);
    }

    public function store(Request $request, Matches $match): JsonResponse
    {
        // organizer.access only constrains Organizers; a Scorer or Admin is
        // held to their own organization's matches here.
        abort_unless(Matches::forUser($request->user())->whereKey($match->id)->exists(), 403);

        $validated = $request->validate([
            'actual_team_id' => 'required|integer',
            'name' => 'required|string|min:2|max:100',
            'mobile_country_code' => 'required|string|max:6',
            'mobile_national_number' => ['required', 'string', 'max:20', 'regex:/^[\d\s\-()]{6,20}$/'],
            // The scorer has seen the phone matches and chose an existing player…
            'use_player_id' => 'nullable|integer',
            // …or confirmed this really is somebody new who shares the phone.
            'force_new' => 'nullable|boolean',
        ], [
            'mobile_national_number.regex' => 'Enter a valid phone number.',
        ]);

        $team = ActualTeam::withoutGlobalScopes()->findOrFail($validated['actual_team_id']);
        $phone = Phone::full($validated['mobile_country_code'], $validated['mobile_national_number']);

        $existing = null;
        if (! empty($validated['use_player_id'])) {
            $existing = $this->quickPlayers->candidates($match, $phone)
                ->firstWhere('id', (int) $validated['use_player_id']);
            abort_if($existing === null, 422, 'That player does not have this phone number.');
        } elseif (! $request->boolean('force_new')) {
            $candidates = $this->quickPlayers->candidates($match, $phone);
            if ($candidates->isNotEmpty()) {
                // Ask before creating a duplicate. Names only — never another
                // player's contact details.
                return response()->json([
                    'status' => 'match',
                    'candidates' => $candidates->map(fn (Player $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                    ])->values(),
                ]);
            }
        }

        try {
            $squadRow = $this->quickPlayers->add(
                $match,
                $team,
                $validated['name'],
                $validated['mobile_country_code'],
                $validated['mobile_national_number'],
                $request->user(),
                $existing,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'added',
            'actual_team_id' => $team->id,
            'option' => [
                'id' => $squadRow->id,
                'name' => $squadRow->player->name,
            ],
        ]);
    }
}
