<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hold a response back as though it were crossing a slow link — for the operator who turned
 * it on, and nobody else.
 *
 * The auction is run in a hall on whatever connection the venue has, and the screens that
 * matter (the organizer panel, a hundred team screens, the LED wall) behave very differently
 * at 1 Mbps than they do on an office desk. There was no way to find that out except on the
 * day. This makes the finding-out happen beforehand.
 *
 * Three properties matter more than the simulation itself:
 *
 *  - **Per-session.** The setting lives in the session, so it throttles one browser. It is not
 *    a global switch, cannot be set for anyone else, and cannot escape to the public wall.
 *  - **It expires.** Every limit carries a deadline. A throttle left on by accident would be
 *    indistinguishable from the auction breaking, on the one day that must not happen — so it
 *    lifts itself, and the expiry is checked here rather than trusted to anyone remembering.
 *  - **It never touches a write.** Only GET responses are held. Slowing a bid down to test a
 *    screen would make the test itself the thing that broke the auction.
 *
 * What it does NOT simulate: static assets. JS, CSS and images are served by nginx and never
 * reach PHP, so a real 1 Mbps link is still slower on first load than this makes it look. The
 * page reports that alongside the control, rather than letting the number be trusted further
 * than it deserves.
 */
class SimulateBandwidth
{
    /** Where the limit lives. One key, so clearing it is unambiguous. */
    public const SESSION_KEY = 'bandwidth_simulator';

    /**
     * The longest a throttle may last.
     *
     * Long enough to walk through a whole lot at 256 kbps; short enough that one forgotten on
     * the morning of an auction has lifted by the time it starts.
     */
    public const MAX_MINUTES = 60;

    /** Never sleep longer than this for one response, whatever the arithmetic says. */
    private const MAX_SLEEP_SECONDS = 15.0;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $limit = self::activeLimitKbps($request);

        if ($limit === null || ! $request->isMethod('GET')) {
            return $response;
        }

        $bytes = self::responseSize($response);

        if ($bytes < 1) {
            return $response;
        }

        /*
         * The time this many bytes would take on that link, minus what the request has already
         * spent. A response that was genuinely slower than the simulated link is left alone —
         * the point is a floor on how slow things feel, not an addition to it.
         */
        $wouldTake = $bytes / ($limit * 1000 / 8);
        $alreadySpent = microtime(true) - (float) ($request->server('REQUEST_TIME_FLOAT') ?: microtime(true));

        $sleep = min(self::MAX_SLEEP_SECONDS, $wouldTake - $alreadySpent);

        if ($sleep > 0.001) {
            usleep((int) ($sleep * 1_000_000));
        }

        // So the page can show what it actually cost, rather than what was asked for.
        $response->headers->set('X-Simulated-Kbps', (string) $limit);
        $response->headers->set('X-Simulated-Bytes', (string) $bytes);
        $response->headers->set('X-Simulated-Delay-Ms', (string) (int) max(0, $sleep * 1000));

        return $response;
    }

    /**
     * The limit in force for this request, or null.
     *
     * Returns null once the deadline has passed AND clears the setting, so an expired throttle
     * disappears from the UI rather than lingering as a switch that claims to be on.
     */
    public static function activeLimitKbps(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state) || empty($state['kbps'])) {
            return null;
        }

        if (! empty($state['expires_at']) && now()->timestamp >= (int) $state['expires_at']) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return (int) $state['kbps'];
    }

    /**
     * Response size in bytes.
     *
     * A streamed or file response has no in-memory content to measure; falling back to the
     * Content-Length header covers the downloads (a card zip, the player workbook) that are
     * the most interesting things to test on a slow link.
     */
    private static function responseSize(Response $response): int
    {
        $content = $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
        || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? ''
            : (string) $response->getContent();

        if ($content !== '') {
            return strlen($content);
        }

        return (int) ($response->headers->get('Content-Length') ?: 0);
    }
}
