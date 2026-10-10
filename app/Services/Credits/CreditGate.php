<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * One-time unlocks at the door of a feature: publish a tournament, run an
 * auction, open the LED wall.
 *
 * allows() answers "can this go ahead now?". Free (package organization, cost
 * 0, Superadmin) or already unlocked → yes, and a free unlock on a credits
 * organization is recorded so a later price rise never charges a tournament
 * that was already using the feature. Otherwise → the confirm page's URL,
 * where the cost, the balance and the balance after are shown before anything
 * is spent.
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
}
