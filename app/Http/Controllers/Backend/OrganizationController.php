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
            $organizations = Organization::with('wallet')->latest()->paginate(10);
        } else {
            $organizations = Organization::with('wallet')->where('id', $user->organization_id)->paginate(10);
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
        ]);

        // Every organization pays per feature in credits; there are no package limits.
        $organization = Organization::create($validated + ['billing_mode' => 'credits']);
        app(\App\Services\Credits\CreditService::class)->welcome($organization);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $this->authorize('organization.edit');

        $tournamentCount = $organization->tournaments()->count();
        $auctionCount = \App\Models\Auction::whereHas('tournament', function ($q) use ($organization) {
            $q->where('organization_id', $organization->id);
        })->count();
        $credits = app(\App\Services\Credits\CreditService::class);

        return view('backend.pages.organizations.edit', [
            'organization' => $organization,
            'tournamentCount' => $tournamentCount,
            'auctionCount' => $auctionCount,
            'balance' => $credits->format($credits->balance($organization)),
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('organization.edit');
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $organization->id,
        ]);

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
