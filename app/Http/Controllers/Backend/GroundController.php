<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ground;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GroundController extends Controller
{
    public function index(Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['ground.view']);

        $user = Auth::user();

        // withCount so a card can say how many matches a venue has hosted —
        // that is also what decides whether Delete is offered at all.
        $query = Ground::with('organization')->withCount('matches');

        if (! $user->hasRole('Superadmin')) {
            $query->where('organization_id', $user->organization_id);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (($status = $request->input('status')) !== null && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        $grounds = $query->orderBy('name')->paginate(24)->withQueryString();

        return view('backend.pages.grounds.index', [
            'grounds' => $grounds,
            'organizations' => $this->assignableOrganizations(),
            'search' => $search,
            'status' => $status,
            'breadcrumbs' => [
                'title' => __('Grounds'),
            ],
        ]);
    }

    /**
     * Grounds are created and edited in a modal on the index page, so these two
     * routes have no view of their own — they used to render
     * `backend.pages.grounds.create`, which has never existed, and any stale
     * link or typed URL produced a 500. Send people to the working editor.
     */
    public function create(): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.create']);

        return redirect()->route('admin.grounds.index', ['action' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.create']);

        $validated = $request->validate($this->rules());

        /*
         * grounds.organization_id is a NOT NULL foreign key, but a Superadmin's
         * own organization_id is NULL — so copying it straight off the user blew
         * up every Superadmin save with
         * "Column 'organization_id' cannot be null". They pick one instead;
         * everyone else stays pinned to their own organization and cannot file a
         * venue under somebody else's.
         */
        $validated['organization_id'] = $this->resolveOrganizationId($request);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['google_maps_link'] = $this->normalizeMapsLink($validated['google_maps_link'] ?? null);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('grounds', 'public');
        }

        $ground = Ground::create($validated);

        return redirect()->route('admin.grounds.index')
            ->with('success', __('Ground ":name" created.', ['name' => $ground->name]));
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

    public function edit(Ground $ground): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.edit']);
        $this->authorizeOrganization($ground);

        return redirect()->route('admin.grounds.index', ['action' => 'edit', 'ground' => $ground->id]);
    }

    public function update(Request $request, Ground $ground): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.edit']);
        $this->authorizeOrganization($ground);

        $validated = $request->validate($this->rules());

        $validated['is_active'] = $request->boolean('is_active');
        $validated['google_maps_link'] = $this->normalizeMapsLink($validated['google_maps_link'] ?? null);

        // Only a Superadmin sees the organization picker; for anyone else the
        // field is absent and the ground keeps the organization it has.
        if (Auth::user()->hasRole('Superadmin') && $request->filled('organization_id')) {
            $validated['organization_id'] = (int) $request->input('organization_id');
        } else {
            unset($validated['organization_id']);
        }

        if ($request->hasFile('image')) {
            if ($ground->image) {
                Storage::disk('public')->delete($ground->image);
            }
            $validated['image'] = $request->file('image')->store('grounds', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($ground->image) {
                Storage::disk('public')->delete($ground->image);
            }
            $validated['image'] = null;
        }

        $ground->update($validated);

        return redirect()->route('admin.grounds.index')
            ->with('success', __('Ground ":name" updated.', ['name' => $ground->name]));
    }

    public function destroy(Ground $ground): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['ground.delete']);
        $this->authorizeOrganization($ground);

        /*
         * matches.ground_id would be orphaned, and the fixture list shows the
         * venue by name — so say how many matches are in the way rather than
         * refusing flatly, and point at the alternative that actually works
         * (deactivating hides it from new fixtures without touching history).
         */
        $matchCount = $ground->matches()->count();
        if ($matchCount > 0) {
            return redirect()->back()->with('error', __(
                'Cannot delete ":name" — :count match(es) are played there. Mark it inactive instead to hide it from new fixtures.',
                ['name' => $ground->name, 'count' => $matchCount]
            ));
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
        if (! $user->hasRole('Superadmin') && $ground->organization_id !== $user->organization_id) {
            abort(403);
        }
    }

    /** Validation shared by store() and update(). */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            /*
             * A bare "maps.app.goo.gl/..." paste is the common case and `url`
             * rejects it, so accept a scheme-less host too and normalise below.
             *
             * The cap must match the column, which is now TEXT: this field was
             * validated at 500 against a varchar(255) and a 257-character Google
             * Maps place URL got through validation only to 500 on the insert.
             */
            'google_maps_link' => 'nullable|string|max:2000',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'is_active' => 'boolean',
            /*
             * Required only when there is genuinely a choice to make: a
             * Superadmin has no organization to inherit, but if the system holds
             * exactly one, demanding they name it is pointless friction — and
             * the form does not even render the picker in that case.
             */
            'organization_id' => $this->mustChooseOrganization()
                ? 'required|exists:organizations,id'
                : 'nullable|exists:organizations,id',
        ];
    }

    /** True when the current user has no organization to inherit and several to pick from. */
    private function mustChooseOrganization(): bool
    {
        $user = Auth::user();

        if ($user->organization_id) {
            return false;
        }

        return Organization::query()->count() > 1;
    }

    /**
     * The organization a new ground belongs to.
     *
     * An org-scoped user always gets their own, whatever the form posted. A
     * Superadmin has none of their own, so they must choose — unless there is
     * only one organization in the system, in which case choosing is pointless.
     */
    private function resolveOrganizationId(Request $request): int
    {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin') && $user->organization_id) {
            return (int) $user->organization_id;
        }

        if ($request->filled('organization_id')) {
            return (int) $request->input('organization_id');
        }

        if ($user->organization_id) {
            return (int) $user->organization_id;
        }

        // Validation has already insisted on a choice when one was needed, so
        // reaching here means there is exactly one organization to fall back to.
        $only = Organization::query()->value('id');
        abort_if(! $only, 422, __('Create an organization before adding grounds.'));

        return (int) $only;
    }

    /**
     * Make a pasted Google Maps link safe to put in an href.
     *
     * People paste "maps.app.goo.gl/xyz" from the share sheet, with no scheme.
     * Stored raw that becomes a relative link that navigates inside the admin
     * panel; `url` validation rejected it outright, which just looked broken.
     * Anything that is not plausibly a link is dropped rather than stored, so a
     * javascript: paste can never reach the page.
     */
    private function normalizeMapsLink(?string $link): ?string
    {
        $link = trim((string) $link);

        if ($link === '') {
            return null;
        }

        if (! preg_match('~^https?://~i', $link)) {
            // Reject anything with another scheme (javascript:, data:, …).
            if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $link)) {
                return null;
            }
            $link = 'https://' . ltrim($link, '/');
        }

        return filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
    }

    /** Organizations this user may file a ground under. */
    private function assignableOrganizations()
    {
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            return Organization::orderBy('name')->get(['id', 'name']);
        }

        return Organization::where('id', $user->organization_id)->get(['id', 'name']);
    }
}
