<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionOperator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who runs an auction, chosen per auction.
 *
 * Adds a user who is NOT a player — an auctioneer, a compere, somebody at the desk — and says
 * what they may do in this one auction. The permission set already splits the panel three ways;
 * this decides which auction each person's split applies to.
 */
class AuctionOperatorController extends Controller
{
    /** The role an operator needs to reach the panel at all. */
    private const ROLE = 'Auctioneer';

    public function index(Auction $auction): View
    {
        $this->authorize('auction.edit');

        /*
         * Candidates exclude players.
         *
         * A player has an account so they can complete their own registration; handing one the
         * controls of the auction they are IN is not a thing to make one click away. Team
         * managers are excluded for the same reason — they have a side.
         */
        $candidates = User::query()
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Player', 'Team Manager', 'Team Owner']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('backend.pages.auctions.operators', [
            'auction' => $auction,
            'operators' => $auction->operators()->with('user:id,name,email')->get(),
            'candidates' => $candidates,
            'abilities' => AuctionOperator::abilities(),
            'breadcrumbs' => [
                'title' => __('Who runs this auction'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'in:' . implode(',', array_keys(AuctionOperator::abilities())),
        ]);

        $user = User::findOrFail($data['user_id']);

        /*
         * The role is granted here rather than asked for beforehand.
         *
         * Reaching the panel needs `auction.observe`, which lives on a role — so an organizer
         * who added somebody and then found they still could not open the auction would have no
         * way of knowing why. Adding the person IS the intent to let them in.
         *
         * assignRole ADDS, it does not replace: a Scorer who is also given tonight's lots stays a
         * Scorer and keeps everything that came with it. `syncRoles` would have replaced the lot,
         * which is how someone loses their real job by being handed a second one.
         */
        if (! $user->hasAnyRole([self::ROLE, 'Superadmin', 'Admin', 'Organizer'])) {
            $user->assignRole(self::ROLE);
        }

        AuctionOperator::updateOrCreate(
            ['auction_id' => $auction->id, 'user_id' => $user->id],
            ['abilities' => array_values($data['abilities'])],
        );

        return back()->with('success', __(':name can now run this auction.', ['name' => $user->name]));
    }

    public function destroy(Auction $auction, AuctionOperator $operator): RedirectResponse
    {
        $this->authorize('auction.edit');
        abort_unless($operator->auction_id === $auction->id, 404);

        /*
         * The row goes; the role stays.
         *
         * The role is what lets them open a panel at all, and they may be running another
         * auction tonight — stripping it here would take them off that one too. With no row for
         * this auction the middleware refuses them, which is the whole point.
         */
        $operator->delete();

        return back()->with('success', __('Removed from this auction.'));
    }
}
