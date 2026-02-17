<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZoneController extends Controller
{
    public function index()
    {
        $this->authorize('zone.view');
        $user = Auth::user();

        $query = Zone::with(['organization', 'tournaments']);

        if ($user->hasRole('Superadmin')) {
            $zones = $query->latest()->paginate(10);
        } else {
            $zones = $query->where('organization_id', $user->organization_id)->latest()->paginate(10);
        }

        return view('backend.pages.zones.index', compact('zones'));
    }

    public function create()
    {
        $this->authorize('zone.create');
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::orderBy('name')->get();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        return view('backend.pages.zones.create', compact('organizations'));
    }

    public function store(Request $request)
    {
        $this->authorize('zone.create');

        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:active,inactive',
            'order' => 'nullable|integer|min:0',
        ]);

        $data = $request->only(['organization_id', 'name', 'description', 'status', 'order']);
        $data['slug'] = Str::slug($request->name) . '-' . Str::random(6);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('zones', 'public');
        }

        Zone::create($data);

        return redirect()->route('admin.zones.index')->with('success', 'Zone created successfully.');
    }

    public function show(Zone $zone)
    {
        $this->authorize('zone.view');

        $zone->load(['organization', 'tournaments']);

        return view('backend.pages.zones.show', compact('zone'));
    }

    public function edit(Zone $zone)
    {
        $this->authorize('zone.edit');
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::orderBy('name')->get();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        return view('backend.pages.zones.edit', compact('zone', 'organizations'));
    }

    public function update(Request $request, Zone $zone)
    {
        $this->authorize('zone.edit');

        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:active,inactive',
            'order' => 'nullable|integer|min:0',
        ]);

        $data = $request->only(['organization_id', 'name', 'description', 'status', 'order']);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($zone->logo) {
                Storage::disk('public')->delete($zone->logo);
            }
            $data['logo'] = $request->file('logo')->store('zones', 'public');
        }

        $zone->update($data);

        return redirect()->route('admin.zones.index')->with('success', 'Zone updated successfully.');
    }

    public function destroy(Zone $zone)
    {
        $this->authorize('zone.delete');

        // Check if zone has tournaments
        if ($zone->tournaments()->count() > 0) {
            return redirect()->route('admin.zones.index')
                ->with('error', 'Cannot delete zone with associated tournaments. Please reassign or delete tournaments first.');
        }

        // Delete logo if exists
        if ($zone->logo) {
            Storage::disk('public')->delete($zone->logo);
        }

        $zone->delete();

        return redirect()->route('admin.zones.index')->with('success', 'Zone deleted successfully.');
    }

    /**
     * Get zones by organization (AJAX)
     */
    public function getByOrganization(Request $request)
    {
        $organizationId = $request->get('organization_id');

        $zones = Zone::where('organization_id', $organizationId)
            ->active()
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($zones);
    }
}
