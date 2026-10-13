@extends('backend.layouts.app')

@section('title')
    {{ __('LED wall') }} — {{ $match->name }} | {{ config('app.name') }}
@endsection

@section('admin-content')
{{--
    The LED wall operator's console. Everything here reads and changes the
    server-side queue (LedQueueService); the wall itself never decides, so what
    this page shows as "Now showing" is what every screen on the link shows.
--}}
<div class="p-4 mx-auto sm:p-6 max-w-7xl">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ __('LED wall') }} · {{ $match->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400" id="scoreLine">…</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Scores are entered on the match page; the console only decides what the screen shows. --}}
            <a href="{{ route('admin.matches.show', $match) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 font-semibold">
                <iconify-icon icon="lucide:pencil-line" width="16"></iconify-icon>{{ __('Enter scores') }}
            </a>
            <a href="{{ $wallUrl }}" target="_blank" rel="noopener" class="px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('Open wall') }}</a>
            <button type="button" onclick="navigator.clipboard?.writeText(@js($wallUrl)); this.textContent='{{ __('Copied') }}'" class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Copy wall link') }}</button>
            <form method="POST" action="{{ route('admin.led.regenerate', $display) }}" onsubmit="return confirm('{{ __('Create a new link? Screens on the current link will stop.') }}')">
                @csrf
                <button class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">{{ __('New link') }}</button>
            </form>
        </div>
    </div>

    {{-- The score strip for live video (OBS / vMix), separate from the big screen. --}}
    @php $tickerUrl = route('public.live-ticker', $match); @endphp
    <div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-semibold text-gray-800 dark:text-white">{{ __('Score ticker for live video') }}</p>
                <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">{{ __('A score strip to lay over your video stream. In OBS or vMix add it as a Browser Source, 1920 × 1080: everything except the strip is see-through. Opened in a normal browser tab the empty part looks white — that is expected. If your mixer cannot use transparency, use the green-screen or black version.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach([[__('Transparent'), $tickerUrl], [__('Green screen'), $tickerUrl . '?bg=green'], [__('Black'), $tickerUrl . '?bg=black']] as [$label, $url])
                    <span class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">{{ $label }}</a>
                        <button type="button" onclick="navigator.clipboard?.writeText(@js($url)); this.textContent='{{ __('Copied') }}'" class="px-2 py-1.5 border-l border-gray-300 dark:border-gray-600 text-xs text-indigo-600">{{ __('Copy') }}</button>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Ticker design: how the live-video strip looks. Changes show in the preview as you make them; Save puts them on air. --}}
    @php $td = $tickerDesign; @endphp
    <details id="tickerDesign" class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
        <summary class="cursor-pointer select-none p-4 flex flex-wrap items-center gap-2">
            <span class="font-semibold text-gray-800 dark:text-white">{{ __('Ticker design') }}</span>
            <span class="text-xs text-gray-500">{{ __('Colours, size, position and what the score ticker shows. Save a design as a template to reuse it.') }}</span>
        </summary>
        <div class="px-4 pb-4 grid grid-cols-1 xl:grid-cols-5 gap-4">
            <form id="tickerForm" class="xl:col-span-2 space-y-4" oninput="ticker.preview()" onsubmit="ticker.save(event)">
                <fieldset class="grid grid-cols-2 gap-3">
                    <legend class="col-span-2 font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Position & size') }}</legend>
                    <label class="flex flex-col gap-1">{{ __('Position') }}
                        <select name="position" class="form-control"><option value="bottom">{{ __('Bottom') }}</option><option value="top">{{ __('Top') }}</option></select></label>
                    <label class="flex flex-col gap-1">{{ __('Alignment') }}
                        <select name="align" class="form-control">
                            <option value="stretch">{{ __('Full width') }}</option><option value="left">{{ __('Left') }}</option>
                            <option value="center">{{ __('Centre') }}</option><option value="right">{{ __('Right') }}</option>
                        </select></label>
                    <label class="flex flex-col gap-1">{{ __('Size') }} <span class="text-xs text-gray-500"><output id="scaleOut"></output>%</span>
                        <input name="scale" type="range" min="50" max="150" step="5"></label>
                    <label class="flex flex-col gap-1">{{ __('Width (not full width), px') }}
                        <input name="width" type="number" min="600" max="1920" class="form-control"></label>
                    <label class="flex flex-col gap-1">{{ __('From the side, px') }}
                        <input name="offset_x" type="number" min="0" max="400" class="form-control"></label>
                    <label class="flex flex-col gap-1">{{ __('From top / bottom, px') }}
                        <input name="offset_y" type="number" min="0" max="400" class="form-control"></label>
                    <label class="flex flex-col gap-1">{{ __('Corner roundness') }}
                        <input name="radius" type="range" min="0" max="40"></label>
                    <label class="flex flex-col gap-1">{{ __('Background opacity') }}
                        <input name="opacity" type="range" min="40" max="100"></label>
                </fieldset>

                <fieldset class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <legend class="col-span-full font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Colours') }}</legend>
                    @foreach(['bg' => __('Background'), 'panel_from' => __('Panel'), 'panel_to' => __('Panel fade'), 'accent_from' => __('Accent bar'), 'accent_to' => __('Accent fade'), 'highlight' => __('Highlight text'), 'text' => __('Text')] as $k => $label)
                        <label class="flex flex-col gap-1 text-xs">{{ $label }}<input name="{{ $k }}" type="color" class="h-9 w-full rounded border border-gray-300 dark:border-gray-600"></label>
                    @endforeach
                    <label class="col-span-full flex items-center gap-2"><input name="team_colours" type="checkbox"> {{ __('Team boxes in each team\'s own colours') }}</label>
                </fieldset>

                <fieldset class="grid grid-cols-2 gap-2">
                    <legend class="col-span-2 font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Show') }}</legend>
                    @foreach(['show_stats' => __('Run rate & target'), 'show_this_over' => __('This over'), 'show_players' => __('Batters & bowler row'), 'show_partnership' => __('Partnership'), 'show_bowler' => __('Bowler'), 'show_status' => __('Status line (last wicket)'), 'show_moments' => __('Console moments (wicket, four, cards…)')] as $k => $label)
                        <label class="flex items-center gap-2"><input name="{{ $k }}" type="checkbox"> {{ $label }}</label>
                    @endforeach
                    <label class="col-span-2 flex items-center gap-2">{{ __('Tournament badge') }}
                        <select name="badge" class="form-control !py-1 min-w-[150px] w-auto"><option value="right">{{ __('Right corner') }}</option><option value="left">{{ __('Left corner') }}</option><option value="hidden">{{ __('Hidden') }}</option></select></label>
                    <p class="col-span-2 text-xs text-gray-500">{{ __('The badge sits in the top corner, or the bottom corner when the strip is at the top.') }}</p>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Save ticker design') }}</button>
                    <label class="flex items-center gap-2"><input name="apply_to_tournament" type="checkbox"> {{ __('Use for every match in this tournament') }}</label>
                    <button type="button" onclick="ticker.reset()" class="text-xs underline text-gray-500">{{ __('Back to standard look') }}</button>
                    <span id="tickerSaved" class="text-green-600 hidden"></span>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2">
                    <p class="font-medium text-gray-700 dark:text-gray-200">{{ __('Templates') }} <span class="text-xs font-normal text-gray-500">— {{ __('saved designs, reusable on any match') }}</span></p>
                    <div class="flex flex-wrap items-end gap-2">
                        <select id="tickerTemplate" class="form-control min-w-[200px] w-auto">
                            @forelse($tickerTemplates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_default ? ' ★' : '' }}{{ $t->isPlatform() ? ' (' . __('for everyone') . ')' : '' }}</option>
                            @empty
                                <option value="">{{ __('No templates yet') }}</option>
                            @endforelse
                        </select>
                        <button type="button" onclick="ticker.applyTemplate()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600" @disabled($tickerTemplates->isEmpty())>{{ __('Load') }}</button>
                        <button type="button" onclick="ticker.deleteTemplate()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600" @disabled($tickerTemplates->isEmpty())>{{ __('Delete') }}</button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <input id="tickerTemplateName" type="text" maxlength="80" class="form-control w-auto" placeholder="{{ __('e.g. Green & gold, top') }}">
                        <label class="flex items-center gap-1 text-xs"><input id="tickerTemplateDefault" type="checkbox"> {{ __('Default for new matches') }}</label>
                        @if($canPlatformPresets)<label class="flex items-center gap-1 text-xs"><input id="tickerTemplatePlatform" type="checkbox"> {{ __('For everyone') }}</label>@endif
                        <button type="button" onclick="ticker.saveTemplate()" class="px-3 py-2 rounded-lg border border-indigo-300 text-indigo-700 dark:border-indigo-700 dark:text-indigo-300">{{ __('Save as template') }}</button>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('Load puts the template on air for this match and in the editor. Save as template saves what is in the editor and keeps it under that name.') }}</p>
                </div>
            </form>

            <div class="xl:col-span-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500">{{ __('Preview — the live ticker with your changes, before you save') }}</span>
                    <button type="button" onclick="ticker.sample()" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600">{{ __('Show a sample moment') }}</button>
                </div>
                <div id="tickerStage" class="relative w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" style="aspect-ratio:16/9;background:linear-gradient(160deg,#3d6b3d,#1f3a24)">
                    <iframe id="tickerPreview" src="{{ route('public.live-ticker', $match) }}?preview=1" title="{{ __('Ticker preview') }}" style="border:0;width:1920px;height:1080px;transform-origin:0 0;position:absolute;left:0;top:0"></iframe>
                </div>
            </div>
        </div>
    </details>

    {{-- How it works: short, so an operator new to the console knows what each control does. --}}
    <div class="mb-4 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-4 text-sm text-indigo-900 dark:text-indigo-100">
        <p class="font-semibold mb-1">{{ __('How this works') }}</p>
        <ul class="list-disc ml-5 space-y-0.5">
            <li><strong>{{ __('Enter scores') }}</strong> — {{ __('scores are entered on the match\'s scoring page (green button above). This console only decides what the screen shows.') }}</li>
            <li>{{ __('This page runs the big LED screen / TV at the ground. Open wall gives the screen\'s link (no sign-in needed on the TV). The preview below is exactly what that screen shows, and every change here reaches it within 1–2 seconds.') }}</li>
            <li><strong>{{ __('Auto') }}</strong> — {{ __('the wall changes screens by itself: wickets, boundaries, end of over, innings break and result, plus your chosen stats between balls. The live score always comes back.') }}</li>
            <li><strong>{{ __('Manual') }}</strong> — {{ __('the wall stays on the live score. Nothing changes until you press a Show now button.') }}</li>
            <li><strong>{{ __('Show now') }}</strong> — {{ __('puts that screen up straight away, then goes back to the live score.') }}</li>
            <li><strong>{{ __('Freeze') }}</strong> — {{ __('keeps the current screen up until you press Unfreeze.') }} <strong>{{ __('Back to live score') }}</strong> — {{ __('clears whatever is showing.') }}</li>
        </ul>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Now showing + preview --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Now showing') }}</h3>
                    <span id="nowTimer" class="text-xs text-gray-500"></span>
                </div>
                <p id="nowLine" class="text-lg font-semibold text-gray-900 dark:text-white">…</p>
                <div class="mt-3 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700" style="aspect-ratio:16/9">
                    <iframe src="{{ $wallUrl }}" title="{{ __('Wall preview') }}" class="w-full h-full" style="border:0"></iframe>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button" onclick="led.control('live')" class="px-3 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700">{{ __('Back to live score') }}</button>
                    <button type="button" id="freezeBtn" onclick="led.toggleFreeze()" class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Freeze') }}</button>
                    <div class="ml-auto flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-sm">
                        <button type="button" id="modeAuto" onclick="led.mode('auto')" class="px-3 py-2">{{ __('Auto') }}</button>
                        <button type="button" id="modeManual" onclick="led.mode('manual')" class="px-3 py-2">{{ __('Manual') }}</button>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Show now') }}</h3>
                <p class="text-xs text-gray-500 mb-3">{{ __('Tap a screen to put it on the wall now. It shows for the "Match moment time" below, then the live score returns.') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($panels as $key => $label)
                        <button type="button" onclick="led.push(@js($key))" class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-100">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Up next --}}
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Up next') }}</h3>
                <p class="text-xs text-gray-500 mb-3">{{ __('Check each item before it airs. Held items wait for you.') }}</p>
                <ol id="queue" class="space-y-2"></ol>
                <p id="queueEmpty" class="text-sm text-gray-500">{{ __('Nothing queued — the live score is up.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Recently') }}</h3>
                <ol id="recent" class="space-y-1 text-sm text-gray-500"></ol>
            </div>
        </div>
    </div>

    {{-- Settings --}}
    <form id="settingsForm" class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 grid grid-cols-2 md:grid-cols-4 gap-4 items-end text-sm" onsubmit="led.saveSettings(event)">
        <h3 class="col-span-full font-semibold text-gray-800 dark:text-white">{{ __('Settings') }}</h3>
        <label class="flex flex-col gap-1">{{ __('Score shows between panels (s)') }}<input name="rotation_seconds" type="number" min="5" max="300" class="form-control"></label>
        <label class="flex flex-col gap-1">{{ __('Filler panel time (s)') }}<input name="panel_seconds" type="number" min="3" max="60" class="form-control"></label>
        <label class="flex flex-col gap-1">{{ __('Match moment time (s)') }}<input name="event_seconds" type="number" min="3" max="60" class="form-control"></label>
        <label class="flex flex-col gap-1">{{ __('Wait before airing (s)') }}<input name="air_delay_seconds" type="number" min="0" max="60" class="form-control"></label>
        <label class="flex items-center gap-2"><input name="require_approval" type="checkbox" value="1"> {{ __('Hold match moments for my approval') }}</label>
        <label class="flex items-center gap-2"><input name="reduced_motion" type="checkbox" value="1"> {{ __('No animations') }}</label>
        <label class="flex flex-col gap-1">{{ __('Theme') }}
            <select name="theme" class="form-control">
                <option value="dark">{{ __('Plain dark') }}</option>
                <option value="light">{{ __('Plain light') }}</option>
                @foreach(\App\Services\Poster\Presets\PresetThemes::all() as $t)
                    <option value="{{ $t['key'] }}">{{ $t['name'] }}</option>
                @endforeach
            </select>
        </label>
        <fieldset class="col-span-full flex flex-wrap gap-4">
            <legend class="mb-1 text-gray-600 dark:text-gray-300">{{ __('Rotate between balls') }}</legend>
            @foreach($fillers as $f)
                <label class="flex items-center gap-2"><input type="checkbox" name="fillers[]" value="{{ $f }}"> {{ $panels[$f] ?? $f }}</label>
            @endforeach
        </fieldset>
        <label class="col-span-full flex items-center gap-2"><input name="apply_to_tournament" type="checkbox" value="1"> {{ __('Use these settings for every match in this tournament') }}</label>
        <div class="col-span-full"><button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">{{ __('Save settings') }}</button> <span id="savedNote" class="text-green-600 hidden">{{ __('Saved') }}</span></div>
    </form>

    {{-- Presets: saved setups reused across matches and tournaments. --}}
    <div class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-sm">
        <h3 class="font-semibold text-gray-800 dark:text-white">{{ __('Presets') }}</h3>
        <p class="text-xs text-gray-500 mb-3">
            {{ __('A preset is a saved setup — timings, stats to rotate and theme. Apply one to this match, or save the settings above as a preset to reuse on other matches and tournaments. New matches start with the tournament\'s last setup, or the default preset.') }}
            @if($canPlatformPresets) {{ __('Presets marked "for everyone" are offered to every organization.') }} @endif
        </p>
        <div class="flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1">{{ __('Preset') }}
                <select id="presetSelect" class="form-control min-w-[220px]">
                    @forelse($presets as $p)
                        <option value="{{ $p->id }}" data-platform="{{ $p->isPlatform() ? 1 : 0 }}">{{ $p->name }}{{ $p->isPlatform() ? ' · ' . __('for everyone') : '' }}{{ $p->is_default ? ' · ' . __('default') : '' }}</option>
                    @empty
                        <option value="">{{ __('No presets yet') }}</option>
                    @endforelse
                </select>
            </label>
            <button type="button" onclick="led.applyPreset()" class="px-3 py-2 rounded-lg bg-indigo-600 text-white" @disabled($presets->isEmpty())>{{ __('Apply to this match') }}</button>
            <button type="button" onclick="led.deletePreset()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600" @disabled($presets->isEmpty())>{{ __('Delete') }}</button>
            <span class="mx-2 h-8 w-px bg-gray-200 dark:bg-gray-700"></span>
            <label class="flex flex-col gap-1">{{ __('Save current settings as') }}
                <input id="presetName" type="text" maxlength="80" class="form-control" placeholder="{{ __('e.g. Final night') }}">
            </label>
            <label class="flex items-center gap-2"><input id="presetDefault" type="checkbox"> {{ __('Default for new matches') }}</label>
            @if($canPlatformPresets)
                <label class="flex items-center gap-2"><input id="presetPlatform" type="checkbox"> {{ __('For everyone') }}</label>
            @endif
            <button type="button" onclick="led.savePreset()" class="px-3 py-2 rounded-lg border border-indigo-300 text-indigo-700 dark:border-indigo-700 dark:text-indigo-300">{{ __('Save preset') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('backend.pages.auction.partials.echo-init')
<script>
// Ticker design editor: the form drives the preview by postMessage; Save stores it.
const ticker = (() => {
    const base = @json(url('/admin/led/' . $display->id));
    const csrf = @json(csrf_token());
    const DEFAULTS = @json(\App\Services\LedWall\TickerDesign::DEFAULTS);
    const TOGGLES = @json(\App\Services\LedWall\TickerDesign::TOGGLES);
    const form = () => document.getElementById('tickerForm');
    const frame = () => document.getElementById('tickerPreview');

    function fill(d) {
        const f = form();
        Object.entries(d).forEach(([k, v]) => {
            const el = f.elements[k];
            if (!el) return;
            if (el.type === 'checkbox') el.checked = !!v; else el.value = v;
        });
        preview();
    }
    function read() {
        const f = form(), d = {};
        Object.keys(DEFAULTS).forEach(k => {
            const el = f.elements[k];
            if (!el) return;
            d[k] = el.type === 'checkbox' ? el.checked : (typeof DEFAULTS[k] === 'number' ? +el.value : el.value);
        });
        return d;
    }
    function preview() {
        const d = read();
        document.getElementById('scaleOut').textContent = d.scale;
        form().elements.width.disabled = d.align === 'stretch';
        frame().contentWindow?.postMessage({ type: 'ticker-design', design: d }, location.origin);
    }
    function fit() {
        const stage = document.getElementById('tickerStage');
        frame().style.transform = `scale(${stage.clientWidth / 1920})`;
    }
    const send = (path, body) => fetch(base + path, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body),
    }).then(async r => { const j = await r.json().catch(() => ({})); if (!r.ok) alert(j.message || 'That did not work.'); return j; });

    document.addEventListener('DOMContentLoaded', () => {
        fill(@json($tickerDesign));
        frame().addEventListener('load', preview);
        fit(); window.addEventListener('resize', fit);
        document.getElementById('tickerDesign').addEventListener('toggle', fit);
    });

    return {
        preview,
        sample: () => frame().contentWindow?.postMessage({ type: 'ticker-sample' }, location.origin),
        reset: () => fill(DEFAULTS),
        save(e) {
            e.preventDefault();
            send('/ticker', { ...read(), apply_to_tournament: form().elements.apply_to_tournament.checked }).then(j => {
                if (!j.ok) return;
                const n = document.getElementById('tickerSaved');
                n.textContent = j.others ? `Saved — on air, and on ${j.others} other match${j.others === 1 ? '' : 'es'}` : 'Saved — on air';
                n.classList.remove('hidden'); setTimeout(() => n.classList.add('hidden'), 4000);
            });
        },
        applyTemplate() {
            const id = document.getElementById('tickerTemplate').value;
            if (!id) return;
            // Loads into the editor and on air (a template is a finished design).
            send(`/presets/${id}/apply`).then(j => { if (j.ok && j.design) fill(j.design); });
        },
        deleteTemplate() {
            const sel = document.getElementById('tickerTemplate');
            if (!sel.value || !confirm('Delete the template "' + sel.selectedOptions[0].textContent.trim() + '"?')) return;
            fetch(base + '/presets/' + sel.value, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } })
                .then(r => r.ok ? location.reload() : r.json().then(j => alert(j.message || 'That did not work.')));
        },
        saveTemplate() {
            const name = document.getElementById('tickerTemplateName').value.trim();
            if (!name) { alert('Give the template a name.'); return; }
            // A template stores the saved design: save first so what you see is what is kept.
            send('/ticker', read()).then(j => j.ok && send('/presets', {
                name, kind: 'ticker', is_default: document.getElementById('tickerTemplateDefault').checked,
                platform: !!document.getElementById('tickerTemplatePlatform')?.checked,
            })).then(j => { if (j && j.ok) location.reload(); });
        },
    };
})();

const led = (() => {
    const base = @json(url('/admin/led/' . $display->id));
    const csrf = @json(csrf_token());
    let data = null, timer = null, settingsLoaded = false;

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const post = (path, body = {}) => fetch(base + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    }).then(async r => { const j = await r.json().catch(() => ({})); if (!r.ok) alert(j.message || 'That did not work.'); load(); return j; });

    const btn = (label, action, id, cls = '') => `<button type="button" class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 ${cls}" onclick="led.item(${id}, '${action}')">${label}</button>`;

    function render() {
        document.getElementById('scoreLine').textContent = data.score;
        const now = data.now;
        document.getElementById('nowLine').textContent = now.id ? `${now.label} — ${now.summary}` : `${now.label} — ${now.summary}`;
        document.getElementById('nowTimer').textContent = data.display.frozen ? 'Frozen' : (now.ends_in ? `ends in ${now.ends_in}s` : '');
        document.getElementById('freezeBtn').textContent = data.display.frozen ? 'Unfreeze' : 'Freeze';
        document.getElementById('modeAuto').className = 'px-3 py-2 ' + (data.display.mode === 'auto' ? 'bg-indigo-600 text-white' : '');
        document.getElementById('modeManual').className = 'px-3 py-2 ' + (data.display.mode === 'manual' ? 'bg-indigo-600 text-white' : '');

        const q = document.getElementById('queue');
        q.innerHTML = data.queue.map(i => `<li class="rounded-lg border ${i.status === 'held' ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-700'} p-2">
            <div class="flex items-center justify-between text-xs text-gray-500"><span>${esc(i.label)}${i.pinned ? ' · pinned' : ''}${i.source === 'manual' ? ' · manual' : ''}</span>
            <span>${i.status === 'held' ? 'waiting for you' : (i.airs_in > 0 ? 'airs in ' + i.airs_in + 's' : 'next')}</span></div>
            <div class="text-sm font-medium text-gray-900 dark:text-white my-1">${esc(i.summary)}</div>
            <div class="flex flex-wrap gap-1">
              ${i.status === 'held' ? btn('Approve', 'approve', i.id, 'bg-green-600 text-white border-green-600') : btn('Hold', 'hold', i.id)}
              ${btn('Skip', 'skip', i.id, 'text-red-600')}
              ${btn(i.pinned ? 'Unpin' : 'Pin', i.pinned ? 'unpin' : 'pin', i.id)}
              ${btn('↑', 'up', i.id)}${btn('↓', 'down', i.id)}
            </div></li>`).join('');
        document.getElementById('queueEmpty').style.display = data.queue.length ? 'none' : '';
        document.getElementById('recent').innerHTML = data.recent.map(i => `<li>${i.status === 'skipped' ? '✕' : '✓'} ${esc(i.label)} — ${esc(i.summary)}</li>`).join('');

        if (!settingsLoaded) {
            const f = document.getElementById('settingsForm'), s = data.display.settings;
            ['rotation_seconds', 'panel_seconds', 'event_seconds', 'air_delay_seconds'].forEach(k => f.elements[k].value = s[k]);
            f.elements.require_approval.checked = !!s.require_approval;
            f.elements.reduced_motion.checked = !!s.reduced_motion;
            f.elements.theme.value = s.theme;
            f.querySelectorAll('input[name="fillers[]"]').forEach(c => c.checked = (s.fillers || []).includes(c.value));
            settingsLoaded = true;
        }
    }

    async function load() {
        clearTimeout(timer);
        try {
            const r = await fetch(base + '/state', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (r.ok) { data = await r.json(); render(); }
        } finally {
            // Every 2 s: the countdowns are the point of this page.
            timer = setTimeout(load, 2000);
        }
    }

    // Push only speeds things up; if it fails the 2 s poll carries on, and the
    // buttons below must never depend on it.
    try {
        window.pushChannel?.('led.{{ $display->id }}')?.listen('.led.changed', load);
        window.pushChannel?.('match.{{ $match->id }}')?.listen('.score.updated', load);
    } catch (e) {
        console.warn('[led] live updates unavailable, polling only:', e);
    }
    load();

    return {
        item: (id, action) => post(`/items/${id}/${action}`),
        push: panel => post('/push', { panel }),
        control: c => post('/control/' + c),
        mode: m => post('/control/mode', { mode: m }),
        toggleFreeze: () => post('/control/freeze', { frozen: !data?.display.frozen }),
        saveSettings(e) {
            e.preventDefault();
            const f = e.target, body = {
                rotation_seconds: +f.elements.rotation_seconds.value, panel_seconds: +f.elements.panel_seconds.value,
                event_seconds: +f.elements.event_seconds.value, air_delay_seconds: +f.elements.air_delay_seconds.value,
                require_approval: f.elements.require_approval.checked, reduced_motion: f.elements.reduced_motion.checked,
                theme: f.elements.theme.value, fillers: [...f.querySelectorAll('input[name="fillers[]"]:checked')].map(c => c.value),
                apply_to_tournament: f.elements.apply_to_tournament.checked,
            };
            post('/settings', body).then(j => {
                if (!j.ok) return;
                const n = document.getElementById('savedNote');
                n.textContent = j.others ? `Saved — also applied to ${j.others} other match${j.others === 1 ? '' : 'es'}` : 'Saved';
                n.classList.remove('hidden'); setTimeout(() => n.classList.add('hidden'), 3000);
            });
        },
        applyPreset() {
            const id = document.getElementById('presetSelect').value;
            if (!id) return;
            // The form re-reads the wall's settings after the preset lands.
            post(`/presets/${id}/apply`).then(j => { if (j.ok) { settingsLoaded = false; load(); } });
        },
        deletePreset() {
            const sel = document.getElementById('presetSelect');
            if (!sel.value || !confirm('Delete the preset "' + sel.selectedOptions[0].textContent.trim() + '"?')) return;
            fetch(base + '/presets/' + sel.value, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } })
                .then(r => r.ok ? location.reload() : r.json().then(j => alert(j.message || 'That did not work.')));
        },
        savePreset() {
            const name = document.getElementById('presetName').value.trim();
            if (!name) { alert('Give the preset a name.'); return; }
            post('/presets', {
                name, is_default: document.getElementById('presetDefault').checked,
                platform: !!document.getElementById('presetPlatform')?.checked,
            }).then(j => { if (j.ok) location.reload(); });
        },
    };
})();
</script>
@endpush
