<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Support\Str;

/**
 * Content-Security-Policy for pages that render admin-authored HTML.
 *
 * Why this matters: the public auction display is served from the same origin as the
 * admin app. Session cookies are httpOnly, so they cannot be read — but a script running
 * same-origin can fetch an admin page, scrape the CSRF token out of it and POST anything
 * the *viewer* is allowed to do. The organizer opens this screen from the auction page
 * while logged in, so authored JavaScript would run with their privileges.
 *
 * The nonce is what closes that. Our own runtime carries it; nothing in the authored
 * body does, so the browser refuses to execute any <script> in the template, any
 * external script src, and any inline on*= handler — without us having to parse,
 * sanitise or restrict the author's markup, which is what keeps the feature usable.
 *
 * `object-src`/`base-uri`/`form-action` close the plugin, base-hijack and phishing-form
 * paths; `connect-src 'self'` stops beacons. External images and Google Fonts stay
 * allowed because broadcast templates genuinely need them and the page is public anyway.
 */
class AddTemplateCsp
{
    /**
     * A fresh nonce for one response.
     *
     * Must be generated per render, never reused: a predictable nonce is the same as no
     * nonce at all.
     */
    public static function nonce(): string
    {
        return Str::random(24);
    }

    /**
     * The policy for a page rendering admin-authored markup.
     *
     * Applied ONLY to that page. It was briefly attached to the whole
     * /auction/{auction}/live route, which broke the ordinary LED wall: that page is our
     * own Blade and pulls Tailwind, confetti, Pusher and Echo from CDNs, all of which
     * `default-src 'self'` blocks — so it rendered as unstyled blankness.
     */
    public static function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            // The platform runtime carries the nonce; anything in the authored body does
            // not, so the browser refuses to execute it.
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            'img-src \'self\' data: https:',
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'none'",
            "frame-ancestors 'self'",
        ]);
    }
}
