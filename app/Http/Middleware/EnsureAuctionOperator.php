<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuctionOperator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Narrows an auctioneer to the auction they were actually given.
 *
 * The permissions already split the panel — observe, control, sell — and the routes enforce that
 * split. What a permission cannot say is WHICH auction, so without this a person trusted to call
 * one evening's lots could open any auction in the organization and start selling in it.
 *
 * Runs after the permission middleware, and only narrows: it never grants. Somebody who reaches
 * a route already holds the permission for it; this asks the second question, "on this auction?"
 *
 * Deliberately silent for admins, organizers and superadmins. They own the auctions and are the
 * ones who hand these rows out — narrowing them by an absent row is how the people who set an
 * event up get locked out of it an hour before it starts.
 *
 * Usage: `->middleware('auction.operator:control')` on a route that carries {auction}.
 */
class EnsureAuctionOperator
{
    public function handle(Request $request, Closure $next, string $ability = AuctionOperator::ABILITY_OBSERVE): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        /*
         * Only Auctioneers are narrowed. Everybody else is left exactly as they were.
         *
         * This first refused anyone who was not an admin or organizer, which reads sensibly and
         * is wrong: any custom role carrying auction permissions — and there are such roles in
         * use — would have been locked out of every auction the moment this deployed, without
         * appearing in any list. It took the whole test suite down, which is the cheap version
         * of the same discovery.
         *
         * The rule that holds is additive: this middleware exists to scope a NEW role, so it
         * scopes that role and nothing else. Anyone who could run an auction yesterday still
         * can; an Auctioneer can run only the auctions they are named on.
         */
        if (! $user->hasRole('Auctioneer')) {
            return $next($request);
        }

        // An Auctioneer who is also an admin or organizer keeps the wider access.
        if ($user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return $next($request);
        }

        /*
         * Being made an auctioneer ADDS to what somebody already had; it never takes anything away.
         *
         * Adding a person to an auction grants them the Auctioneer role on top of their existing
         * roles — but if this then narrowed them, a Scorer or a custom role that already carried
         * auction access would silently LOSE it everywhere the moment they were named on one
         * auction. Handing somebody an extra job must not quietly cost them the one they had.
         *
         * So: if any role other than Auctioneer already grants auction access, that access stands
         * and this stands aside. The narrowing applies to what the Auctioneer role alone opens.
         */
        $otherAuctionRole = $user->roles
            ->reject(fn ($role) => $role->name === 'Auctioneer')
            ->contains(fn ($role) => $role->permissions->contains(
                fn ($permission) => str_starts_with($permission->name, 'auction.')
            ));

        if ($otherAuctionRole) {
            return $next($request);
        }

        $auction = $request->route('auction');
        $auctionId = is_object($auction) ? ($auction->id ?? null) : $auction;

        // No auction in the route means nothing to scope to. Refusing here would break routes
        // this was never meant to guard, so it stands aside rather than guessing.
        if (! $auctionId) {
            return $next($request);
        }

        $operator = AuctionOperator::where('auction_id', $auctionId)
            ->where('user_id', $user->id)
            ->first();

        if ($operator && $operator->can($ability)) {
            return $next($request);
        }

        /*
         * Named in the refusal, because "403" on a live auction screen tells an auctioneer
         * nothing about who to ask or what they are missing.
         */
        $message = $operator
            ? 'You can open this auction, but not ' . $this->describe($ability) . '. Ask the organizer to add it.'
            : 'You have not been added to this auction. Ask the organizer to add you as an operator.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        abort(403, $message);
    }

    private function describe(string $ability): string
    {
        return match ($ability) {
            AuctionOperator::ABILITY_CONTROL => 'take bids on it',
            AuctionOperator::ABILITY_SELL => 'sell or undo in it',
            AuctionOperator::ABILITY_POOLS => 'start or reopen its pools',
            AuctionOperator::ABILITY_SCREENS => 'control its screens',
            default => 'do that in it',
        };
    }
}
