<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Auction;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Credits\PosterMeter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * credits.posters — charge for every poster this request produces, against
 * the tournament in its route (directly, or through its match, auction or
 * registration). Refuses up front when even one poster is unaffordable, so the
 * user is told before a render rather than after it.
 */
class MeterPosters
{
    public function __construct(private readonly PosterMeter $meter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tournament = $this->tournament($request);
        if (! $tournament) {
            return $next($request);
        }

        $this->meter->begin($tournament);
        try {
            $this->meter->ensureCanAfford(1);

            return $next($request);
        } finally {
            $this->meter->end();
        }
    }

    private function tournament(Request $request): ?Tournament
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
