<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Poster\Presets\PresetThemes;
use App\Services\Poster\TemplateLibrary;
use App\Services\Poster\TemplatePresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Template presets (see TemplateLibrary): designed in the same drag editor as a
 * tournament's own templates; only the URLs it saves to differ.
 *
 * Only Super Admin builds presets (`template.presets`): the global ones, and any
 * organization's own. Everyone else only PICKS from the presets offered to their
 * organization — on the new tournament and new season forms, and "Add from
 * Presets" on a tournament they can edit — so the picture of a preset and the
 * copy into a tournament are open to them too.
 *
 * Ready-made designs come in here as starting points, and a tournament's
 * template can be saved as a preset; tournaments pick presets when they are
 * created, when a season starts, or later from their Templates page.
 */
class TemplateLibraryController extends Controller
{
    /** Preset files live apart from any tournament's, so a delete can be checked against it. */
    private const DIR = 'tournament_templates/library';

    public function __construct(private readonly TemplateLibrary $library)
    {
        $this->middleware('permission:template.presets')->except(['thumb', 'copyToTournament']);
        // Picking presets: whoever creates tournaments or edits a tournament's templates.
        $this->middleware('permission:template.presets|tournament.create|template.manage')->only('thumb');
        $this->middleware('permission:template.presets|template.manage')->only('copyToTournament');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $this->scopeFrom($request, $user);
        $isSuper = $user->hasRole('Superadmin');
        $presets = app(TemplatePresetService::class);

        return view('backend.pages.template-library.index', [
            'scope' => $scope,
            'scopeParam' => $scope ?? 'global',
            'scopeName' => $scope ? Organization::find($scope)?->name : __('Global'),
            'organizations' => $isSuper ? Organization::orderBy('name')->get(['id', 'name']) : collect(),
            'isSuper' => $isSuper,
            'templates' => $templates = $this->library->scope($scope)->orderBy('type')->orderByDesc('is_default')->orderBy('name')->get()->groupBy('type'),
            'usage' => $templates->flatten()->mapWithKeys(fn ($t) => [$t->id => $this->library->usage($t)]),
            // Shown read-only under an organization's own: what its tournaments are offered too.
            'global' => $scope !== null ? $this->library->scope(null)->orderBy('type')->orderBy('name')->get()->groupBy('type') : collect(),
            'types' => TournamentTemplate::TYPES,
            'readyMade' => collect($presets->all())->sortBy([['type', 'asc'], ['sort', 'asc']])->groupBy('type'),
            'themes' => PresetThemes::all(),
            'sourceTournaments' => $this->sourceTournaments($user, $scope),
            'breadcrumbs' => ['title' => __('Template presets')],
        ]);
    }

    public function create(Request $request): View
    {
        $scope = $this->scopeFrom($request, $request->user());
        $type = $request->get('type', TournamentTemplate::TYPE_WELCOME_CARD);
        abort_unless(in_array($type, TournamentTemplate::TYPES, true), 404);
        [$defaultCanvasWidth, $defaultCanvasHeight] = TournamentTemplate::defaultCanvas($type);

        return view('backend.pages.tournaments.templates.editor', [
            'template' => null,
            'type' => $type,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($type),
            'defaultCanvasWidth' => $defaultCanvasWidth,
            'defaultCanvasHeight' => $defaultCanvasHeight,
            'editorUrls' => $this->editorUrls(null, $scope),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $scope = $this->scopeFrom($request, $request->user());
        $validated = $this->validated($request, creating: true);

        $template = TournamentTemplate::create($validated + [
            'tournament_id' => null,
            'organization_id' => $scope,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($validated['type']),
            'is_default' => $this->library->needsDefault($scope, $validated['type']),
            'is_active' => true,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Preset created.',
                'redirect' => route('admin.template-library.edit', $template),
            ]);
        }

        return $this->backToScope($template)->with('success', 'Preset created.');
    }

    public function edit(Request $request, TournamentTemplate $template): View
    {
        $this->authorizeEdit($request, $template);

        return view('backend.pages.tournaments.templates.editor', [
            'template' => $template,
            'type' => $template->type,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($template->type),
            'editorUrls' => $this->editorUrls($template, $template->organization_id),
        ]);
    }

    public function update(Request $request, TournamentTemplate $template): JsonResponse|RedirectResponse
    {
        $this->authorizeEdit($request, $template);
        $validated = $this->validated($request, creating: false);

        // A new background replaces the old file: preset files are never shared (TemplateCopier).
        if (isset($validated['background_image']) && $template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }

        // A new version: tournaments' copies now show they are behind it.
        $template->update($validated + ['design_version' => (int) $template->design_version + 1]);
        $usage = $this->library->usage($template);
        $message = $usage['copies'] > 0
            ? "Preset saved. {$usage['copies']} tournament template(s) use it — press Update tournaments on the presets page to bring them up to date."
            : 'Preset saved. Tournaments created from now on get this version.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return $this->backToScope($template)->with('success', $message);
    }

    public function destroy(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $this->authorizeEdit($request, $template);

        if ($template->background_image
            && ! TournamentTemplate::where('background_image', $template->background_image)->where('id', '!=', $template->id)->exists()) {
            Storage::disk('public')->delete($template->background_image);
        }
        $template->delete();

        return back()->with('success', 'Preset deleted. Tournaments that already have a copy keep it.');
    }

    /**
     * A copy to change. Super Admin's stays in the same scope; anyone else's lands in
     * their own organization — which is how an organization makes a global preset its own.
     */
    public function duplicate(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $template);
        $scope = $user->hasRole('Superadmin') ? $template->organization_id : $this->ownOrganization($user);

        $name = $template->organization_id === $scope ? $template->name . ' (Copy)' : $template->name;
        $copy = $this->library->copyAsPreset($template, $scope, $name);

        return redirect()->route('admin.template-library.edit', $copy)->with('success', "Copied as \"{$copy->name}\".");
    }

    public function setDefault(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $this->authorizeEdit($request, $template);
        $this->library->makeDefault($template);

        return back()->with('success', "\"{$template->name}\" is now the default {$this->typeLabel($template)} for new tournaments.");
    }

    public function toggleActive(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $this->authorizeEdit($request, $template);
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active
            ? "\"{$template->name}\" is ticked by default when a tournament is created."
            : "\"{$template->name}\" is still offered, but no longer ticked by default.");
    }

    /** Free or Premium (with its price), and which organizations a global preset is offered to. */
    public function settings(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $this->authorizeEdit($request, $template);
        $data = $request->validate([
            'tier' => ['required', Rule::in([TournamentTemplate::TIER_FREE, TournamentTemplate::TIER_PREMIUM])],
            'price' => 'nullable|integer|min:0|max:1000000',
            'offer' => ['nullable', Rule::in(['all', 'selected'])],
            'offered_to' => 'nullable|array',
            'offered_to.*' => 'integer|exists:organizations,id',
        ]);

        $premium = $data['tier'] === TournamentTemplate::TIER_PREMIUM;
        if ($premium && (int) ($data['price'] ?? 0) <= 0) {
            return back()->with('error', 'A premium preset needs a price in credits.');
        }

        $offered = null;
        if ($template->organization_id === null && ($data['offer'] ?? 'all') === 'selected') {
            $offered = array_values(array_unique(array_map('intval', $data['offered_to'] ?? [])));
            if (! $offered) {
                return back()->with('error', 'Pick at least one organization, or offer it to all.');
            }
        }

        $template->forceFill([
            'tier' => $data['tier'],
            'price' => $premium ? (int) $data['price'] : 0,
            'offered_to' => $offered,
        ])->save();

        return back()->with('success', "\"{$template->name}\" saved: " . ($premium ? "Premium, {$template->price} credits per tournament" : 'Free')
            . ($offered ? ', offered to ' . count($offered) . ' organization(s).' : '.'));
    }

    /** Bring the tournaments' copies of a preset up to its current design (see TemplateLibrary::syncCopies). */
    public function sync(Request $request, TournamentTemplate $template): RedirectResponse
    {
        $this->authorizeEdit($request, $template);
        $result = $this->library->syncCopies($template);

        return back()->with('success', "Updated {$result['updated']} tournament template(s)."
            . ($result['skipped'] ? " {$result['skipped']} edited by their tournament were left as they are; they show \"update available\"." : ''));
    }

    /** Super Admin: move a preset between global and an organization. */
    public function move(Request $request, TournamentTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Superadmin') && $template->tournament_id === null, 403);
        $scope = $this->scopeFrom($request, $request->user());

        if ($scope !== $template->organization_id) {
            $template->update([
                'organization_id' => $scope,
                'name' => $this->library->uniqueName($template->name, $scope),
                'is_default' => $this->library->needsDefault($scope, $template->type),
            ]);
        }

        return back()->with('success', "\"{$template->name}\" moved.");
    }

    /** A ready-made design, added to a scope as a preset to edit. */
    public function addReadyMade(Request $request, TemplatePresetService $presets): RedirectResponse
    {
        $scope = $this->scopeFrom($request, $request->user());
        $data = $request->validate([
            'preset' => ['required', 'string', Rule::in(array_keys($presets->all()))],
            'theme' => ['nullable', 'string', Rule::in(array_keys(PresetThemes::all()))],
        ]);

        $template = $presets->addAsPreset($data['preset'], $data['theme'] ?? null, $scope);

        return redirect()->route('admin.template-library.edit', $template)
            ->with('success', "\"{$template->name}\" added to your presets. Change anything, then save.");
    }

    public function readyMadeThumb(string $key, string $theme, TemplatePresetService $presets)
    {
        abort_unless($presets->find($key) !== null, 404);
        $path = $presets->thumbnail($key, $theme === 'own' ? null : $theme);
        abort_unless($path, 404);

        return response()->file(Storage::disk('public')->path($path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function thumb(Request $request, TournamentTemplate $template)
    {
        $this->authorizeView($request->user(), $template);
        $path = $this->library->thumbnail($template);

        if (! $path) {
            abort_unless($template->background_image && Storage::disk('public')->exists($template->background_image), 404);
            $path = $template->background_image;
        }

        // The URL carries the design version (?v=), so a new design is a new URL.
        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => $request->has('v') ? 'private, max-age=31536000, immutable' : 'private, max-age=300']);
    }

    /** A previous tournament's templates, as pictures, to start a preset from. */
    public function tournamentTemplates(Request $request): JsonResponse
    {
        $tournament = Tournament::findOrFail((int) $request->query('tournament'));
        $this->authorizeSource($request->user(), $tournament);

        return response()->json([
            'templates' => $tournament->templates()->orderBy('type')->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (TournamentTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'type' => TournamentTemplate::getTypeDisplay($t->type),
                    'thumb' => route('admin.template-library.source-thumb', $t),
                ])->values(),
        ]);
    }

    public function sourceThumb(Request $request, TournamentTemplate $template)
    {
        abort_if($template->tournament_id === null, 404);
        $this->authorizeSource($request->user(), $template->tournament()->firstOrFail());

        $path = $this->library->thumbnail($template);
        if (! $path) {
            abort_unless($template->background_image && Storage::disk('public')->exists($template->background_image), 404);
            $path = $template->background_image;
        }

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Start a preset from a previous tournament's template: a copy in the scope, opened in the editor. */
    public function importFromTournament(Request $request, TournamentTemplate $template): RedirectResponse
    {
        abort_if($template->tournament_id === null, 404);
        $user = $request->user();
        $this->authorizeSource($user, $template->tournament()->firstOrFail());

        $copy = $this->library->saveFromTournament($template, $this->scopeFrom($request, $user));

        return redirect()->route('admin.template-library.edit', $copy)
            ->with('success', "\"{$copy->name}\" copied from {$template->tournament->name}. Change anything, then save.");
    }

    /** "Save as preset" on a tournament's template: a copy, files and all. */
    public function saveFromTournament(Request $request, Tournament $tournament, TournamentTemplate $template): RedirectResponse
    {
        abort_if($template->tournament_id !== $tournament->id, 404);
        $user = $request->user();
        $this->authorizeTournament($user, $tournament);
        $scope = $user->hasRole('Superadmin') ? $this->scopeFrom($request, $user) : $this->ownOrganization($user);

        $copy = $this->library->saveFromTournament($template, $scope);
        $where = $scope === null ? 'global presets' : 'presets of ' . (Organization::find($scope)?->name ?? 'the organization');

        return back()->with('success', "Saved to the {$where} as \"{$copy->name}\".");
    }

    /** Copy the ticked presets into an existing tournament. */
    public function copyToTournament(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($request->user(), $tournament);
        $data = $request->validate(['presets' => 'required|array|min:1', 'presets.*' => 'integer']);

        try {
            $added = count($this->library->copyInto($tournament, $data['presets']));
        } catch (\App\Services\Credits\InsufficientCreditsException $e) {
            return back()->with('error', $e->getMessage() . ' Premium presets are paid once per tournament. Top up from the Credits page.');
        }

        return back()->with('success', "{$added} preset(s) added to {$tournament->name}.");
    }

    public function uploadOverlay(Request $request): JsonResponse
    {
        $request->validate(['overlay_image' => 'required|file|mimes:png,jpg,jpeg,gif,svg,webp|max:5120']);
        $path = $request->file('overlay_image')->store(self::DIR . '/overlays', 'public');

        return response()->json(['success' => true, 'path' => $path, 'url' => asset('storage/' . $path)]);
    }

    public function deleteOverlay(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->input('path');

        if (! str_starts_with($path, self::DIR . '/') || str_contains($path, '..')) {
            return response()->json(['success' => false, 'error' => 'Invalid path'], 403);
        }
        Storage::disk('public')->delete($path);

        return response()->json(['success' => true]);
    }

    /**
     * The scope a request works in. Super Admin picks one (`scope`: "global" or an
     * organization id). Everyone else always works in their own organization.
     */
    private function scopeFrom(Request $request, User $user): ?int
    {
        if (! $user->hasRole('Superadmin')) {
            return $this->ownOrganization($user);
        }

        $scope = (string) $request->input('scope', 'global');
        if ($scope === 'global' || $scope === '') {
            return null;
        }
        abort_unless(ctype_digit($scope) && Organization::whereKey((int) $scope)->exists(), 404);

        return (int) $scope;
    }

    private function ownOrganization(User $user): int
    {
        abort_unless($user->organization_id, 403, 'Your account is not part of an organization, so it has no presets of its own.');

        return (int) $user->organization_id;
    }

    private function authorizeEdit(Request $request, TournamentTemplate $template): void
    {
        abort_if($template->tournament_id !== null, 404);
        abort_unless($this->library->canEdit($request->user(), $template), 403, 'Only Super Admin can change a global preset. Copy it into your own presets to change it.');
    }

    private function authorizeView(User $user, TournamentTemplate $template): void
    {
        abort_if($template->tournament_id !== null, 404);
        abort_unless(
            $user->hasRole('Superadmin') || $template->organization_id === null || (int) $template->organization_id === (int) $user->organization_id,
            404
        );
    }

    private function authorizeTournament(User $user, Tournament $tournament): void
    {
        abort_unless($user->hasRole('Superadmin') || (int) $tournament->organization_id === (int) $user->organization_id, 403);
    }

    /**
     * Tournaments a user can start presets from: the ones they can see (Tournament::forUser),
     * narrowed to the organization being edited, and only those with templates.
     */
    private function sourceTournaments(User $user, ?int $scope)
    {
        return Tournament::forUser($user)
            ->when($scope !== null, fn ($q) => $q->where('organization_id', $scope))
            ->whereHas('templates')
            ->withCount('templates')
            ->orderByDesc('start_date')->orderByDesc('id')
            ->get(['id', 'name', 'start_date', 'organization_id']);
    }

    private function authorizeSource(User $user, Tournament $tournament): void
    {
        abort_unless(Tournament::forUser($user)->whereKey($tournament->id)->exists(), 404);
    }

    private function backToScope(TournamentTemplate $template): RedirectResponse
    {
        return redirect()->route('admin.template-library.index', ['scope' => $template->organization_id ?? 'global']);
    }

    private function typeLabel(TournamentTemplate $template): string
    {
        return strtolower(TournamentTemplate::getTypeDisplay($template->type));
    }

    private function editorUrls(?TournamentTemplate $template, ?int $scope): array
    {
        return [
            'owner' => __('Template presets'),
            'back' => route('admin.template-library.index', ['scope' => $scope ?? 'global']),
            'save' => $template ? route('admin.template-library.update', $template) : route('admin.template-library.store', ['scope' => $scope ?? 'global']),
            'upload_overlay' => route('admin.template-library.upload-overlay'),
            'delete_overlay' => route('admin.template-library.delete-overlay'),
        ];
    }

    /** The editor's save payload, as TournamentTemplateController reads it. */
    private function validated(Request $request, bool $creating): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(TournamentTemplate::TYPES)],
            'background_image' => 'nullable|image|max:5120',
            'background_image_base64' => 'nullable|string',
            'layout_json' => 'nullable|json',
            'overlay_images' => 'nullable|json',
            'canvas_width' => 'nullable|integer|min:540|max:2160',
            'canvas_height' => 'nullable|integer|min:540|max:3840',
        ]);
        // The editor posts its type on every save; a template's type is fixed once made.
        if (! $creating) {
            unset($validated['type']);
        }

        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')->store(self::DIR, 'public');
        } elseif ($request->filled('background_image_base64')) {
            $validated['background_image'] = $this->saveBase64Image($request->input('background_image_base64'));
        } else {
            unset($validated['background_image']);
        }
        unset($validated['background_image_base64']);

        foreach (['layout_json', 'overlay_images'] as $json) {
            if (isset($validated[$json])) {
                $validated[$json] = json_decode($validated[$json], true);
            }
        }

        return $validated;
    }

    private function saveBase64Image(string $data): string
    {
        $extension = 'png';
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $m)) {
            $extension = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $data = substr($data, strpos($data, ',') + 1);
        }
        $path = self::DIR . '/bg_' . uniqid() . '.' . $extension;
        Storage::disk('public')->put($path, base64_decode($data));

        return $path;
    }
}
