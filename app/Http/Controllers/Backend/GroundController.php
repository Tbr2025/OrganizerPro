<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ground;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GroundController extends Controller
{
    public function index(): View
    {
        $this->checkAuthorization(Auth::user(), ['ground.view']);

        $user = Auth::user();

        $query = Ground::with('organization')->latest();

        if (!$user->hasRole('Superadmin')) {
            $query->where('organization_id', $user->organization_id);
        }

        $grounds = $query->paginate(20);

        return view('backend.pages.grounds.index', [
            'grounds' => $grounds,
            'breadcrumbs' => [
                'title' => __('Grounds'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->checkAuthorization(Auth::user(), ['ground.create']);

        return view('backend.pages.grounds.create', [
            'breadcrumbs' => [
                'title' => __('Add Ground'),
                'items' => [
                    ['label' => __('Grounds'), 'url' => route('admin.grounds.index')],
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.create']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'google_maps_link' => 'nullable|url|max:500',
            'image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'is_active' => 'boolean',
        ]);

        $validated['organization_id'] = Auth::user()->organization_id;
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('grounds', 'public');
        }

        Ground::create($validated);

        return redirect()->route('admin.grounds.index')->with('success', __('Ground created successfully.'));
    }

    public function show(Ground $ground): View
    {
        $this->checkAuthorization(Auth::user(), ['ground.view']);
        $this->authorizeOrganization($ground);

        $matchCount = $ground->matches()->count();
        $upcomingMatches = $ground->matches()
            ->with(['tournament', 'teamA', 'teamB'])
            ->where('status', 'upcoming')
            ->orderBy('match_date')
            ->limit(10)
            ->get();

        return view('backend.pages.grounds.show', [
            'ground' => $ground,
            'matchCount' => $matchCount,
            'upcomingMatches' => $upcomingMatches,
            'breadcrumbs' => [
                'title' => $ground->name,
                'items' => [
                    ['label' => __('Grounds'), 'url' => route('admin.grounds.index')],
                ],
            ],
        ]);
    }

    public function edit(Ground $ground): View
    {
        $this->checkAuthorization(Auth::user(), ['ground.edit']);
        $this->authorizeOrganization($ground);

        return view('backend.pages.grounds.edit', [
            'ground' => $ground,
            'breadcrumbs' => [
                'title' => __('Edit Ground'),
                'items' => [
                    ['label' => __('Grounds'), 'url' => route('admin.grounds.index')],
                ],
            ],
        ]);
    }

    public function update(Request $request, Ground $ground): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.edit']);
        $this->authorizeOrganization($ground);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'google_maps_link' => 'nullable|url|max:500',
            'image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            if ($ground->image) {
                Storage::disk('public')->delete($ground->image);
            }
            $validated['image'] = $request->file('image')->store('grounds', 'public');
        }

        $ground->update($validated);

        return redirect()->route('admin.grounds.index')->with('success', __('Ground updated successfully.'));
    }

    public function destroy(Ground $ground): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.delete']);
        $this->authorizeOrganization($ground);

        // Check if ground has associated matches
        if ($ground->matches()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete ground with associated matches.'));
        }

        if ($ground->image) {
            Storage::disk('public')->delete($ground->image);
        }

        $ground->delete();

        return redirect()->route('admin.grounds.index')->with('success', __('Ground deleted successfully.'));
    }

    private function authorizeOrganization(Ground $ground): void
    {
        $user = Auth::user();
        if (!$user->hasRole('Superadmin') && $ground->organization_id !== $user->organization_id) {
            abort(403);
        }
    }
}
