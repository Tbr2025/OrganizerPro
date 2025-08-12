<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::latest()->paginate(10);

        return view('backend.pages.tournaments.index', [
            'tournaments' => $tournaments,
            'breadcrumbs' => [
                'title' => __('Tournaments'),
            ],
        ]);
    }
    public function create()
    {
        // 1. Get the currently authenticated user
        $user = Auth::user();

        // Safety check: if no user is logged in, deny access.
        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        // 2. Check if the user has the 'Superadmin' role
        if ($user->hasRole('Superadmin')) {
            // Superadmin can see ALL organizations to choose from.
            $organizations = Organization::all();
        } else {
            // For any other user, scope the data by their organization_id.

            // Safety check: ensure the non-admin user is actually assigned to an organization.
            if (!$user->organization_id) {
                // If not, they can't create a tournament. Redirect back with an informative error.
                return redirect()->back()->with('error', 'You are not assigned to an organization and cannot create a tournament.');
            }

            // Get ONLY the organization that belongs to this user.
            // We use ->get() to ensure the result is a collection, so the view's loop works.
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        // 3. Prepare the rest of the data for the view
        // Initially, no organization is selected, so locations are empty. This is correct.
        $locations = collect();

        // 4. Return the view with the correctly scoped data.
        return view('backend.pages.tournaments.create', [
            'organizations' => $organizations,
            'locations' => $locations,
            'breadcrumbs' => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Create Tournament'],
            ],
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'           => 'required|string|max:255',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        Tournament::create($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament created successfully.');
    }

    public function edit(Tournament $tournament)
    {
        $organizations = Organization::all();

        return view('backend.pages.tournaments.edit', [
            'tournament'    => $tournament,
            'organizations' => $organizations,
            'breadcrumbs'   => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Edit Tournament'],
            ],
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name'           => 'required|string|max:255',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        $tournament->update($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament updated successfully.');
    }


    public function destroy(Tournament $tournament)
    {
        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament deleted successfully.');
    }
}
