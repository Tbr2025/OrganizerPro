<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Organization;
use App\Models\Tournament;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function index()
    {
        $breadcrumbs = ['title' => __('Auctions')];
        $auctions = Auction::latest()->paginate(15);

        return view('backend.pages.auctions.index', compact('auctions', 'breadcrumbs'));
    }

    public function create()
    {
        $organizations = Organization::all();
        $tournaments = Tournament::all();
        $breadcrumbs = ['title' => 'Create Auction'];

        return view('backend.pages.auctions.create', compact('organizations', 'tournaments', 'breadcrumbs'));
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
            'tournament_id' => 'required|exists:tournaments,id',
            'start_at'          => 'required|date',

            'end_at'            => 'nullable|date|after_or_equal:start_at',
            'base_price'        => 'nullable|numeric|min:0',
            'max_bid_per_player' => 'nullable|numeric|min:0',
            'max_budget_per_team' => 'nullable|numeric|min:0',
        ]);

        Auction::create($validated);

        return redirect()
            ->route('admin.auctions.index')
            ->with('success', 'Auction created successfully.');
    }


    public function show(Auction $auction)
    {
        $breadcrumbs = ['title' => __('Auction Details')];
        return view('backend.pages.auctions.show', compact('auction', 'breadcrumbs'));
    }

    public function edit(Auction $auction)
    {
        $breadcrumbs = ['title' => __('Edit Auction')];
        return view('backend.pages.auctions.edit', compact('auction', 'breadcrumbs'));
    }

    public function update(Request $request, Auction $auction)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time'   => 'nullable|date|after_or_equal:start_time',
        ]);

        $auction->update($request->only('name', 'start_time', 'end_time', 'base_price', 'max_bid_per_player', 'max_budget_per_team'));

        return redirect()->route('admin.auctions.index')->with('success', 'Auction updated successfully.');
    }

    public function destroy(Auction $auction)
    {
        $auction->delete();
        return redirect()->route('admin.auctions.index')->with('success', 'Auction deleted successfully.');
    }
}
