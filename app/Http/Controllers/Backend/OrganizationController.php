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
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
        ]);

        Organization::create($request->all());

        return redirect()->route('admin.organizations.index')->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $this->authorize('organization.edit');
        return view('backend.pages.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('organization.edit');
        $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name,' . $organization->id,
        ]);

        $organization->update($request->all());

        return redirect()->route('admin.organizations.index')->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('organization.delete');
        $organization->delete();
        return redirect()->route('admin.organizations.index')->with('success', 'Organization deleted successfully.');
    }
}