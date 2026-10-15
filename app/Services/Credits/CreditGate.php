<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Auction;
use App\Models\Credits\CreditFeature;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One-time unlocks at the door of a feature: publish a tournament, run an
 * auction, open the LED wall.
 *
 * allows() answers "can this go ahead now?". Free (package organization, cost
 * 0) or already unlocked → yes, and a free unlock on a credits organization is
 * recorded so a later price rise never charges a tournament that was already
 * using the feature. Otherwise → the confirm page's URL, where the cost, the
 * balance and the balance after are shown before anything is spent.
 *
 * A Superadmin goes ahead without paying, and without recording an unlock:
 * support work on a client's tournament must not hand the client the feature
 * for free.
 */
class CreditGate
{
    public function __construct(private readonly CreditService $credits) {}

    /** null = go ahead; a string = send the user to this confirm page first. */
    public function allows(string $featureKey, Tournament $tournament, string $returnUrl, array $then = [], ?User $actor = null): ?string
    {
        $actor ??= Auth::user();
        $org = $tournament->organization;

        if (! $org || ! $org->usesCredits()) {
            return null;
        }

        if ($this->credits->hasUnlock($org, $featureKey, $tournament)) {
            return null;
        }

        if ($actor && $actor->hasRole('Superadmin')) {
            return null;
        }

        if ($this->credits->quote($org, $featureKey, 1, $actor)['cost'] === 0) {
            $this->credits->unlock($org, $featureKey, $tournament, $actor);

            return null;
        }

        return route('admin.credits.unlock', array_merge([
            'feature' => $featureKey,
            'tournament' => $tournament->id,
            'return' => $returnUrl,
        ], $then));
    }

    /**
     * What this feature costs the organization now, for a price on a button
     * ("Create tournament (100 Credits)"). 0 when free, or for a Superadmin.
     */
    public function fee(string $featureKey, ?Organization $org, ?User $actor = null): int
    {
        return $org ? $this->credits->quote($org, $featureKey, 1, $actor ?? Auth::user())['cost'] : 0;
    }

    /** The fee as a label: "100 Credits", or null when free. */
    public function feeLabel(string $featureKey, ?Organization $org, ?User $actor = null): ?string
    {
        $fee = $this->fee($featureKey, $org, $actor);

        return $fee > 0 ? $this->credits->format($fee) : null;
    }

    /**
     * Create a tournament and pay for it in one go: the tournament fee
     * (CreditFeature::TOURNAMENT_PUBLISH) is charged as it is created, so it can
     * then be published without another charge. Not enough credits → nothing is
     * created and InsufficientCreditsException is thrown. A Superadmin creates
     * without paying.
     *
     * @param  callable(): Tournament  $create
     */
    public function createTournament(Organization $org, callable $create, ?User $actor = null): Tournament
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($org, $create, $actor) {
            $tournament = $create();
            if ($this->credits->charges($org, $actor)) {
                $this->credits->unlock($org, CreditFeature::TOURNAMENT_PUBLISH, $tournament, $actor);
            }

            return $tournament;
        });
    }

    /** The tournament a route acts on: directly, or through its match, auction or registration. */
    public static function tournamentFromRoute(Request $request): ?Tournament
    {
        foreach ($request->route()?->parameters() ?? [] as $param) {
            $t = match (true) {
                $param instanceof Tournament => $param,
                $param instanceof Matches, $param instanceof Auction, $param instanceof TournamentRegistration => $param->tournament,
                default => null,
            };
            if ($t) {
                return $t;
            }
        }

        return null;
    }
}
