<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    public function index()
    {
        $this->authorize('organization.view');
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::latest()->paginate(10);
        } else {
            $organizations = Organization::where('id', $user->organization_id)->paginate(10);
        }

        return view('backend.pages.organizations.index', compact('organizations'));
    }

    public function create()
    {
        $this->authorize('organization.create');
        return view('backend.pages.organizations.create');
    }

    public function store(Request $request)
    {
        $this->authorize('organization.create');
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'package_type' => 'nullable|in:starter,premium,enterprise',
            'max_tournaments' => 'nullable|integer|min:1',
            'auction_enabled' => 'nullable|boolean',
            'auction_modes' => 'nullable|array',
            'auction_modes.*' => 'in:open,closed,offline',
        ]);

        if (!auth()->user()->hasRole('Superadmin')) {
            unset($validated['package_type'], $validated['max_tournaments'], $validated['auction_enabled'], $validated['auction_modes']);
        }

        $validated['auction_enabled'] = $validated['auction_enabled'] ?? false;

        Organization::create($validated);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $this->authorize('organization.edit');

        $tournamentCount = $organization->tournaments()->count();
        $auctionCount = \App\Models\Auction::whereHas('tournament', function ($q) use ($organization) {
            $q->where('organization_id', $organization->id);
        })->count();

        return view('backend.pages.organizations.edit', compact('organization', 'tournamentCount', 'auctionCount'));
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('organization.edit');
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $organization->id,
            'package_type' => 'nullable|in:starter,premium,enterprise',
            'max_tournaments' => 'nullable|integer|min:1',
            'auction_enabled' => 'nullable|boolean',
            'auction_modes' => 'nullable|array',
            'auction_modes.*' => 'in:open,closed,offline',
        ]);

        if (!auth()->user()->hasRole('Superadmin')) {
            unset($validated['package_type'], $validated['max_tournaments'], $validated['auction_enabled'], $validated['auction_modes']);
        }

        if (array_key_exists('auction_enabled', $validated)) {
            $validated['auction_enabled'] = $validated['auction_enabled'] ?? false;
        }

        // Handle "unlimited" toggle — if max_tournaments not in request, set to null
        if (auth()->user()->hasRole('Superadmin') && !$request->has('max_tournaments')) {
            $validated['max_tournaments'] = null;
        }

        $organization->update($validated);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('organization.delete');

        $hasTournaments = $organization->tournaments()->exists();
        $hasUsers = \App\Models\User::where('organization_id', $organization->id)->exists();

        if ($hasTournaments || $hasUsers) {
            return redirect()->route('admin.organizations.index')
                ->with('error', 'Cannot delete organization that has active tournaments or assigned users. Please reassign or remove them first.');
        }

        $organization->delete();
        return redirect()->route('admin.organizations.index')->with('success', 'Organization deleted successfully.');
    }
}