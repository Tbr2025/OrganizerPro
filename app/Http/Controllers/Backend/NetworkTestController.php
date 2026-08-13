<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SimulateBandwidth;
use Illuminate\Http\Request;

/**
 * Rehearse the auction on the connection the hall actually has.
 *
 * A venue running an auction over shared wifi behaves nothing like an office desk, and until
 * now the only way to discover that was on the day. This throttles the operator's own browser
 * so every auction screen can be walked through at 256 kbps, or 1 Mbps, or whatever the site
 * survey says — before a hundred people are watching.
 */
class NetworkTestController extends Controller
{
    /**
     * The presets, in kbps.
     *
     * Chosen to bracket a real venue rather than to be round numbers: 256 is a link in
     * trouble, 1000 is the uplink a hall commonly has, 4000 is a decent one. "No limit" is
     * the absence of a setting, not a preset.
     */
    public const PRESETS = [
        256 => '256 kbps — a link in trouble',
        512 => '512 kbps — a busy hall',
        1000 => '1 Mbps — typical venue uplink',
        2000 => '2 Mbps',
        4000 => '4 Mbps — a good connection',
    ];

    public function index(Request $request)
    {
        $this->authorize('auction.view');

        return view('backend.pages.network-test.index', [
            'presets' => self::PRESETS,
            'active' => SimulateBandwidth::activeLimitKbps($request),
            'expiresAt' => $request->session()->get(SimulateBandwidth::SESSION_KEY . '.expires_at')
                ?? ($request->session()->get(SimulateBandwidth::SESSION_KEY)['expires_at'] ?? null),
            'maxMinutes' => SimulateBandwidth::MAX_MINUTES,
        ]);
    }

    /**
     * Turn a limit on, or off.
     *
     * `kbps` of 0 (or absent) means no limit, which is the same thing as forgetting the
     * setting entirely — there is no "off but remembered" state to get confused by.
     */
    public function update(Request $request)
    {
        $this->authorize('auction.view');

        $validated = $request->validate([
            /*
             * `min:0`, NOT `min:32`.
             *
             * Zero is the "No limit" option — the way the throttle is turned OFF. Floored at 32
             * this validation rejected that post outright, the session kept its old limit, and
             * the one control that has to work no matter what silently did nothing. A feature
             * whose off switch can fail is worse than not having the feature.
             *
             * The floor is enforced below instead, where it can say why.
             */
            'kbps' => 'nullable|integer|min:0|max:100000',
            'minutes' => 'nullable|integer|min:1|max:' . SimulateBandwidth::MAX_MINUTES,
        ]);

        $kbps = (int) ($validated['kbps'] ?? 0);

        // Below this nothing loads at all, and the test tells you nothing you did not know.
        if ($kbps > 0 && $kbps < 32) {
            return back()->withErrors(['kbps' => 'Pick at least 32 kbps — below that nothing loads at all.']);
        }

        if ($kbps < 1) {
            $request->session()->forget(SimulateBandwidth::SESSION_KEY);

            return back()->with('success', 'Bandwidth limit removed — this browser is back to full speed.');
        }

        $minutes = (int) ($validated['minutes'] ?? 15);

        /*
         * Always stamped with a deadline.
         *
         * A throttle left on is indistinguishable from the auction being broken, and it would
         * be discovered at the worst possible moment. Expiry is not a convenience here; it is
         * the thing that makes the feature safe to have at all.
         */
        $request->session()->put(SimulateBandwidth::SESSION_KEY, [
            'kbps' => $kbps,
            'expires_at' => now()->addMinutes($minutes)->timestamp,
        ]);

        return back()->with(
            'success',
            sprintf('Simulating %d kbps for the next %d minute(s) — in this browser only.', $kbps, $minutes)
        );
    }
}
