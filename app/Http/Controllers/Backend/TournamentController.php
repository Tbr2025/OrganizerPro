<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TournamentController extends Controller
{
    public function index(Request $request) // <-- Inject the Request object
    {
        // 1. Get the currently authenticated user
        $user = Auth::user();

        // 2. Start building the Tournament query and eager-load relationships
        $query = Tournament::with(['organization', 'zone']);

        // 3. Apply role-based scoping (filter by organization if not Superadmin)
        if (!$user->hasRole('Superadmin')) {
            if ($user->organization_id) {
                $query->where('organization_id', $user->organization_id);
            } else {
                $query->whereRaw('1 = 0'); // Return no results if no org assigned
            }
        }

        // **THIS IS THE NEW CODE FOR THE SEARCH FEATURE**
        // 4. Apply search filter if a search term is provided in the request
        $searchTerm = $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('location', 'like', '%' . $searchTerm . '%');

                // If the user is a Superadmin, they can also search by organization name
                if (Auth::user()->hasRole('Superadmin')) {
                    $q->orWhereHas('organization', function ($orgQuery) use ($searchTerm) {
                        $orgQuery->where('name', 'like', '%' . $searchTerm . '%');
                    });
                }
            });
        }

        // 5. Execute the final query and paginate the results
        $tournaments = $query->latest()->paginate(10);

        // 6. Return the view with the correctly scoped and filtered data
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

        // 4. Load zones for the user's organization (for non-Superadmin)
        // For Superadmin, zones will be loaded via AJAX when organization is selected
        if ($user->hasRole('Superadmin')) {
            $zones = collect(); // Will be populated via AJAX
        } else {
            $zones = Zone::where('organization_id', $user->organization_id)
                ->active()
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        }

        // 5. Return the view with the correctly scoped data.
        return view('backend.pages.tournaments.create', [
            'organizations' => $organizations,
            'locations' => $locations,
            'zones' => $zones,
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
            'zone_id'        => 'nullable|exists:zones,id',
            'name'           => 'required|string|max:255',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        // Handle empty zone_id
        if (empty($validated['zone_id'])) {
            $validated['zone_id'] = null;
        }

        Tournament::create($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament created successfully.');
    }

    public function edit(Tournament $tournament)
    {
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::all();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        // Load zones for the tournament's organization
        $zones = Zone::where('organization_id', $tournament->organization_id)
            ->active()
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return view('backend.pages.tournaments.edit', [
            'tournament'    => $tournament,
            'organizations' => $organizations,
            'zones'         => $zones,
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
            'zone_id'        => 'nullable|exists:zones,id',
            'name'           => 'required|string|max:255',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        // Handle empty zone_id
        if (empty($validated['zone_id'])) {
            $validated['zone_id'] = null;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($tournament->logo && \Storage::disk('public')->exists($tournament->logo)) {
                \Storage::disk('public')->delete($tournament->logo);
            }
            $validated['logo'] = $request->file('logo')->store('tournaments/logos', 'public');
        }

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
