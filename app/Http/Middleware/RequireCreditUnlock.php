<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Credits\CreditGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * credits.unlock:<feature> — a module paid once per tournament (live scoring).
 * Not unlocked yet → the confirm page, where the cost and the balance are shown
 * before anything is spent; an AJAX call gets a 402 with that page's link.
 */
class RequireCreditUnlock
{
    public function __construct(private readonly CreditGate $gate) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tournament = CreditGate::tournamentFromRoute($request);
        if (! $tournament) {
            return $next($request);
        }

        $return = $request->isMethod('GET') && ! $request->expectsJson() ? $request->fullUrl() : url()->previous();
        $unlockUrl = $this->gate->allows($feature, $tournament, $return);
        if (! $unlockUrl) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            $name = \App\Models\Credits\CreditFeature::where('key', $feature)->value('name') ?? $feature;

            return response()->json([
                'success' => false,
                'message' => "{$name} is not unlocked for this tournament yet. Unlock it first.",
                'unlock_url' => $unlockUrl,
            ], 402);
        }

        return redirect($unlockUrl);
    }
}
