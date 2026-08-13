<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Run work once the response is already on its way to the browser.
 *
 * Broadcasting is the reason this exists. `ShouldBroadcastNow` means exactly what it says: the
 * HTTP call to Pusher happens inline, inside the request, before the response is returned.
 * Measured from this codebase against the configured cluster:
 *
 *     first broadcast in a request:  ~1160 ms   (DNS + TLS handshake, paid per request —
 *                                                PHP-FPM does not keep the connection alive)
 *     each one after that:            ~290 ms
 *
 * A raise announces twice, so placing a bid sat on roughly **1.45 seconds of somebody else's
 * network** before the organizer's own screen heard that it had worked. That is the lag in the
 * room: not the poll, not the panel, but the bidder waiting on a handshake to a third party.
 *
 * Deferring does not make the push arrive later for anyone else — the same call is made, a few
 * milliseconds afterwards. It only stops the person who acted from waiting for it.
 *
 * Under PHP-FPM the response is flushed before `terminating` callbacks run, which is what makes
 * this a real gain rather than a reordering. `php artisan serve` and the queue have no such
 * split, so the work simply runs at the end.
 */
class AfterResponse
{
    /**
     * @param  callable(): void  $work
     */
    public static function run(callable $work): void
    {
        /*
         * Console and test runs do it immediately.
         *
         * There is no response to get out of the way of, and a test that dispatches a bid and
         * then asserts the broadcast must not have to know about the terminating stack to see
         * it. Commands are the same: an artisan process may exit before terminate() runs.
         */
        if (app()->runningInConsole()) {
            $work();

            return;
        }

        app()->terminating($work);
    }
}
