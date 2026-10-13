<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Poster\Presets\PresetThemes;
use App\Services\Poster\TemplateCopier;
use App\Services\Poster\TemplateLibrary;
use App\Services\Poster\TemplatePresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin's template library — the poster templates every new tournament
 * starts with (see TemplateLibrary). Designed in the same drag editor as a
 * tournament's own templates; only the URLs it saves to differ.
 *
 * Also the two ways in and out of a tournament: "Save to library" on one of its
 * templates, and "Add library templates" on a tournament that already exists.
 */
class TemplateLibraryController extends Controller
{
    /** Library files live apart from any tournament's, so a delete can be checked against it. */
    private const DIR = 'tournament_templates/library';

    public function __construct(private readonly TemplateLibrary $library)
    {
        // Global designs every organization's new tournaments receive: a platform control.
        $this->middleware('permission:feature.manage');
    }

    public function index(): View
    {
        $presets = app(TemplatePresetService::class);

        return view('backend.pages.template-library.index', [
            'templates' => $this->library->query()->orderBy('type')->orderByDesc('is_default')->orderBy('name')->get()->groupBy('type'),
            'types' => TournamentTemplate::TYPES,
            'presets' => collect($presets->all())->sortBy([['type', 'asc'], ['sort', 'asc']])->groupBy('type'),
            'themes' => PresetThemes::all(),
            'breadcrumbs' => ['title' => __('Template library')],
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->get('type', TournamentTemplate::TYPE_WELCOME_CARD);
        abort_unless(in_array($type, TournamentTemplate::TYPES, true), 404);
        [$defaultCanvasWidth, $defaultCanvasHeight] = TournamentTemplate::defaultCanvas($type);

        return view('backend.pages.tournaments.templates.editor', [
            'template' => null,
            'type' => $type,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($type),
            'defaultCanvasWidth' => $defaultCanvasWidth,
            'defaultCanvasHeight' => $defaultCanvasHeight,
            'editorUrls' => $this->editorUrls(null),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $this->validated($request, creating: true);
        $validated['tournament_id'] = null;
        $validated['placeholders'] = TournamentTemplate::getDefaultPlaceholders($validated['type']);

        $template = TournamentTemplate::create($validated);
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Library template created.',
                'redirect' => route('admin.template-library.edit', $template),
            ]);
        }

        return redirect()->route('admin.template-library.index')->with('success', 'Library template created.');
    }

    public function edit(TournamentTemplate $template): View
    {
        $this->ensureLibrary($template);

        return view('backend.pages.tournaments.templates.editor', [
            'template' => $template,
            'type' => $template->type,
            'placeholders' => TournamentTemplate::getDefaultPlaceholders($template->type),
            'editorUrls' => $this->editorUrls($template),
        ]);
    }

    public function update(Request $request, TournamentTemplate $template): JsonResponse|RedirectResponse
    {
        $this->ensureLibrary($template);
        $validated = $this->validated($request, creating: false);

        // A new background replaces the old file: library files are never shared (TemplateCopier).
        if (isset($validated['background_image']) && $template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }

        $template->update($validated);
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Library template saved. New tournaments get this version.']);
        }

        return redirect()->route('admin.template-library.index')->with('success', 'Library template saved.');
    }

    public function destroy(TournamentTemplate $template): RedirectResponse
    {
        $this->ensureLibrary($template);

        if ($template->background_image
            && ! TournamentTemplate::where('background_image', $template->background_image)->where('id', '!=', $template->id)->exists()) {
            Storage::disk('public')->delete($template->background_image);
        }
        $template->delete();

        return back()->with('success', 'Removed from the library. Tournaments that already have a copy keep it.');
    }

    public function duplicate(TournamentTemplate $template, TemplateCopier $copier): RedirectResponse
    {
        $this->ensureLibrary($template);
        $copy = $copier->copy($template, null, $this->library->uniqueName($template->name . ' (Copy)'));

        return redirect()->route('admin.template-library.edit', $copy)->with('success', "Duplicated as \"{$copy->name}\".");
    }

    public function setDefault(TournamentTemplate $template): RedirectResponse
    {
        $this->ensureLibrary($template);
        $template->setAsDefault();

        return back()->with('success', "\"{$template->name}\" is now the default for new tournaments.");
    }

    public function toggleActive(TournamentTemplate $template): RedirectResponse
    {
        $this->ensureLibrary($template);
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active
            ? "\"{$template->name}\" will be copied into new tournaments."
            : "\"{$template->name}\" stays in the library but new tournaments will not get it.");
    }

    public function addPreset(Request $request, TemplatePresetService $presets): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', 'string', Rule::in(array_keys($presets->all()))],
            'theme' => ['nullable', 'string', Rule::in(array_keys(PresetThemes::all()))],
        ]);

        $template = $presets->addToLibrary($data['preset'], $data['theme'] ?? null);

        return redirect()->route('admin.template-library.index')
            ->with('success', "\"{$template->name}\" added to the library. Open it to make it your own.");
    }

    /** "Save to library" on a tournament's template: a copy, files and all. */
    public function saveFromTournament(Tournament $tournament, TournamentTemplate $template): RedirectResponse
    {
        abort_if($template->tournament_id !== $tournament->id, 404);
        $copy = $this->library->saveFromTournament($template);

        return back()->with('success', "Saved to the template library as \"{$copy->name}\".");
    }

    /** Copy every active library template into an existing tournament. */
    public function copyToTournament(Tournament $tournament): RedirectResponse
    {
        $added = count($this->library->copyInto($tournament));

        return back()->with('success', $added
            ? "{$added} library template(s) added to {$tournament->name}."
            : 'The template library has no active templates yet.');
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

    private function ensureLibrary(TournamentTemplate $template): void
    {
        abort_unless($template->tournament_id === null, 404);
    }

    private function editorUrls(?TournamentTemplate $template): array
    {
        return [
            'owner' => __('Template library'),
            'back' => route('admin.template-library.index'),
            'save' => $template ? route('admin.template-library.update', $template) : route('admin.template-library.store'),
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
            'is_active' => 'boolean',
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
