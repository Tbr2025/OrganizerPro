<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\LedDisplay;
use App\Models\LedQueueItem;
use App\Models\Matches;
use App\Services\LedWall\LedQueueService;
use App\Services\LedWall\TickerDesign;
use App\Services\LedWall\WallState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The LED wall operator's console: what is on screen, what comes next, and the
 * controls to approve, skip, hold, pin, reorder or push a panel.
 *
 * Requires ledwall.operate, and the match must be one the user may see
 * (Matches::forUser) — organizer.access only narrows Organizers.
 */
class LedConsoleController extends Controller
{
    public function __construct(
        private readonly LedQueueService $queue,
        private readonly WallState $wall,
    ) {
        $this->middleware('permission:ledwall.operate');
    }

    /** Open the console for a match, creating its first display if it has none. */
    public function show(Request $request, Matches $match): View|RedirectResponse
    {
        $this->authorizeMatch($match);

        // Credits: the LED wall is unlocked once per tournament.
        $unlockUrl = app(\App\Services\Credits\CreditGate::class)->allows(
            \App\Models\Credits\CreditFeature::LEDWALL, $match->tournament, route('admin.matches.led', $match)
        );
        if ($unlockUrl) {
            return redirect($unlockUrl);
        }

        $display = LedDisplay::where('match_id', $match->id)->whereNull('revoked_at')->oldest()->first()
            ?? LedDisplay::create([
                'tournament_id' => $match->tournament_id,
                'match_id' => $match->id,
                'name' => 'LED wall — ' . $match->name,
                'token' => LedDisplay::newToken(),
                'mode' => 'auto',
                // Reuse: the tournament's last wall, else the default preset.
                'settings' => \App\Models\LedPreset::startingSettings($match->tournament),
                'created_by' => $request->user()->id,
            ]);

        return view('backend.pages.matches.led-console', [
            'match' => $match,
            'display' => $display,
            'wallUrl' => route('public.led.wall', $display->token),
            'panels' => LedDisplay::PANELS,
            'fillers' => LedDisplay::FILLERS,
            'presets' => \App\Models\LedPreset::wall()->availableTo($match->tournament?->organization_id)->get(),
            'tickerTemplates' => \App\Models\LedPreset::ticker()->availableTo($match->tournament?->organization_id)->get(),
            'tickerDesign' => TickerDesign::normalize(($display->settings ?? [])['ticker'] ?? TickerDesign::defaultTemplate($match->tournament?->organization_id)),
            'canPlatformPresets' => $request->user()->can('feature.manage'),
            'breadcrumbs' => [
                'title' => __('LED wall'),
                'items' => [
                    ['label' => __('Matches'), 'url' => route('admin.matches.index')],
                    ['label' => $match->name, 'url' => route('admin.matches.show', $match)],
                ],
            ],
        ]);
    }

    public function state(LedDisplay $display): JsonResponse
    {
        $this->authorizeDisplay($display);

        $now = $this->queue->tick($display);
        $state = $display->match ? $this->wall->forMatch($display->match) : null;

        $item = fn (LedQueueItem $i) => [
            'id' => $i->id,
            'panel' => $i->panel,
            'label' => LedDisplay::PANELS[$i->panel] ?? $i->panel,
            'summary' => $i->summary,
            'status' => $i->status,
            'source' => $i->source,
            'pinned' => $i->pinned,
            'airs_in' => $i->air_after && $i->air_after->isFuture() ? (int) ceil(now()->diffInMilliseconds($i->air_after) / 1000) : 0,
            'ends_in' => $i->ends_at && $i->ends_at->isFuture() ? (int) ceil(now()->diffInMilliseconds($i->ends_at) / 1000) : null,
        ];

        return response()->json([
            'display' => [
                'id' => $display->id,
                'mode' => $display->mode,
                'frozen' => $display->frozen,
                'settings' => array_merge(LedDisplay::DEFAULT_SETTINGS, $display->settings ?? []),
            ],
            'now' => $now ? $item($now) : ['id' => null, 'panel' => 'scoreboard', 'label' => 'Live scoreboard', 'summary' => $this->scoreLine($state), 'status' => 'showing', 'source' => 'auto', 'pinned' => false, 'airs_in' => 0, 'ends_in' => null],
            'queue' => $this->queue->waiting($display)->limit(30)->get()->map($item)->values(),
            'recent' => $display->queue()->whereIn('status', ['shown', 'skipped'])->latest('updated_at')->limit(8)->get()->map($item)->values(),
            'score' => $this->scoreLine($state),
            'status' => $state['status'] ?? 'no_match',
        ]);
    }

    public function action(Request $request, LedDisplay $display, LedQueueItem $item, string $action): JsonResponse
    {
        $this->authorizeDisplay($display);
        abort_unless((int) $item->led_display_id === (int) $display->id, 404);

        match ($action) {
            'approve' => $this->queue->approve($item, $request->user()->id),
            'hold' => $this->queue->hold($item),
            'skip' => $this->queue->skip($item),
            'pin' => $this->queue->pin($item, true),
            'unpin' => $this->queue->pin($item, false),
            'up' => $this->queue->move($item, -1),
            'down' => $this->queue->move($item, 1),
            default => abort(404),
        };

        return response()->json(['ok' => true]);
    }

    public function push(Request $request, LedDisplay $display): JsonResponse
    {
        $this->authorizeDisplay($display);
        $panel = $request->validate(['panel' => ['required', Rule::in(array_keys(LedDisplay::PANELS))]])['panel'];

        if ($panel !== 'scoreboard' && $panel !== 'prematch' && $this->queue->panelPayload($display, $panel) === null) {
            return response()->json(['ok' => false, 'message' => 'Nothing to show on that panel yet.'], 422);
        }

        $this->queue->push($display, $panel, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function control(Request $request, LedDisplay $display, string $control): JsonResponse
    {
        $this->authorizeDisplay($display);

        match ($control) {
            'live' => $this->queue->backToLive($display),
            'mode' => $this->queue->setMode($display, (string) $request->input('mode')),
            'freeze' => $this->queue->freeze($display, $request->boolean('frozen')),
            default => abort(404),
        };

        return response()->json(['ok' => true]);
    }

    public function settings(Request $request, LedDisplay $display): JsonResponse
    {
        $this->authorizeDisplay($display);
        $v = $this->validatedSettings($request);

        $display->update(['settings' => array_merge(LedDisplay::DEFAULT_SETTINGS, $display->settings ?? [], $v)]);
        \App\Events\LedDisplayChanged::announce($display->id);

        // "Use for every match in this tournament": the other walls take it too.
        $others = 0;
        if ($request->boolean('apply_to_tournament')) {
            foreach (LedDisplay::where('tournament_id', $display->tournament_id)->whereKeyNot($display->id)->whereNull('revoked_at')->get() as $other) {
                $other->update(['settings' => array_merge(LedDisplay::DEFAULT_SETTINGS, $other->settings ?? [], $v)]);
                \App\Events\LedDisplayChanged::announce($other->id);
                $others++;
            }
        }

        return response()->json(['ok' => true, 'others' => $others]);
    }

    /**
     * The live-video ticker's design for this match — and, with
     * apply_to_tournament, every other match's in the tournament.
     */
    public function ticker(Request $request, LedDisplay $display): JsonResponse
    {
        $this->authorizeDisplay($display);
        $design = TickerDesign::normalize($request->validate(TickerDesign::rules()));

        $targets = $request->boolean('apply_to_tournament')
            ? LedDisplay::where('tournament_id', $display->tournament_id)->whereNull('revoked_at')->get()
            : collect([$display]);
        foreach ($targets as $d) {
            $d->update(['settings' => array_merge(LedDisplay::DEFAULT_SETTINGS, $d->settings ?? [], ['ticker' => $design])]);
            \App\Events\LedDisplayChanged::announce($d->id);
        }

        return response()->json(['ok' => true, 'others' => $targets->count() - 1, 'design' => $design]);
    }

    /**
     * Save a preset: kind 'wall' (this wall's settings) or 'ticker' (this
     * match's ticker design) — the platform's (Super Admin) or this organization's.
     */
    public function savePreset(Request $request, LedDisplay $display): JsonResponse
    {
        $this->authorizeDisplay($display);
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'kind' => 'nullable|in:' . implode(',', \App\Models\LedPreset::KINDS),
            'platform' => 'boolean',
            'is_default' => 'boolean',
        ]);
        $kind = $data['kind'] ?? 'wall';
        $settings = $kind === 'ticker'
            ? TickerDesign::normalize(($display->settings ?? [])['ticker'] ?? null)
            : array_diff_key(array_merge(LedDisplay::DEFAULT_SETTINGS, $display->settings ?? []), ['ticker' => 1]);
        $platform = $request->boolean('platform');
        abort_if($platform && ! $request->user()->can('feature.manage'), 403, 'Only Super Admin saves presets for everyone.');
        $orgId = $platform ? null : $display->tournament?->organization_id;

        $preset = DB::transaction(function () use ($request, $data, $orgId, $kind, $settings) {
            if ($request->boolean('is_default')) {
                \App\Models\LedPreset::where('kind', $kind)->where('organization_id', $orgId)->update(['is_default' => false]);
            }

            return \App\Models\LedPreset::updateOrCreate(
                ['organization_id' => $orgId, 'kind' => $kind, 'name' => $data['name']],
                ['settings' => $settings, 'is_default' => $request->boolean('is_default'), 'created_by' => $request->user()->id],
            );
        });

        return response()->json(['ok' => true, 'preset' => $preset->only('id', 'kind', 'name', 'is_default', 'organization_id')]);
    }

    /** Put a preset's settings on this wall. */
    public function applyPreset(Request $request, LedDisplay $display, \App\Models\LedPreset $preset): JsonResponse
    {
        $this->authorizeDisplay($display);
        $this->authorizePreset($request, $preset, $display, false);

        // A ticker template changes only the ticker; a wall preset only the wall.
        $current = array_merge(LedDisplay::DEFAULT_SETTINGS, $display->settings ?? []);
        $display->update(['settings' => $preset->kind === 'ticker'
            ? array_merge($current, ['ticker' => TickerDesign::normalize($preset->settings)])
            : array_merge(LedDisplay::DEFAULT_SETTINGS, array_diff_key($preset->settings ?? [], ['ticker' => 1]), ['ticker' => $current['ticker'] ?? null])]);
        \App\Events\LedDisplayChanged::announce($display->id);

        return response()->json(['ok' => true, 'design' => $preset->kind === 'ticker' ? TickerDesign::normalize($preset->settings) : null]);
    }

    public function deletePreset(Request $request, LedDisplay $display, \App\Models\LedPreset $preset): JsonResponse
    {
        $this->authorizeDisplay($display);
        $this->authorizePreset($request, $preset, $display, true);
        $preset->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizePreset(Request $request, \App\Models\LedPreset $preset, LedDisplay $display, bool $changing): void
    {
        if ($preset->isPlatform()) {
            abort_if($changing && ! $request->user()->can('feature.manage'), 403);

            return;
        }
        abort_unless((int) $preset->organization_id === (int) $display->tournament?->organization_id, 403);
    }

    private function validatedSettings(Request $request): array
    {
        $v = $request->validate([
            'rotation_seconds' => 'required|integer|min:5|max:300',
            'panel_seconds' => 'required|integer|min:3|max:60',
            'event_seconds' => 'required|integer|min:3|max:60',
            'air_delay_seconds' => 'required|integer|min:0|max:60',
            'require_approval' => 'boolean',
            'reduced_motion' => 'boolean',
            'theme' => ['required', Rule::in(array_merge(['dark', 'light'], array_keys(\App\Services\Poster\Presets\PresetThemes::all())))],
            'fillers' => 'array',
            'fillers.*' => Rule::in(LedDisplay::FILLERS),
        ]);
        $v['require_approval'] = $request->boolean('require_approval');
        $v['reduced_motion'] = $request->boolean('reduced_motion');
        $v['fillers'] = array_values($v['fillers'] ?? []);

        return $v;
    }

    /** New private link: every screen on the old one stops (404) at its next poll. */
    public function regenerate(LedDisplay $display): RedirectResponse
    {
        $this->authorizeDisplay($display);
        $display->update(['token' => LedDisplay::newToken()]);
        \App\Events\LedDisplayChanged::announce($display->id);

        return back()->with('success', __('A new wall link was created. Screens on the old link have stopped.'));
    }

    private function scoreLine(?array $state): string
    {
        if (! $state) {
            return 'No match selected';
        }
        $s = $state['score'];
        $line = ($state['batting']['short'] ?? '') . " {$s['runs']}/{$s['wickets']} ({$s['overs']})";
        if ($state['chase']) {
            $line .= " · need {$state['chase']['runs_needed']} off {$state['chase']['balls_left']}";
        }
        if ($state['result']) {
            $line = $state['result']['text'];
        }

        return trim($line);
    }

    private function authorizeMatch(Matches $match): void
    {
        abort_unless(Matches::forUser(auth()->user())->whereKey($match->id)->exists(), 403);
    }

    private function authorizeDisplay(LedDisplay $display): void
    {
        abort_unless($display->match_id, 404);
        $this->authorizeMatch($display->match);
    }
}
