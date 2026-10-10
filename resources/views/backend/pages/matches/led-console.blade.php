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
            <a href="{{ $wallUrl }}" target="_blank" rel="noopener" class="px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('Open wall') }}</a>
            <button type="button" onclick="navigator.clipboard?.writeText(@js($wallUrl)); this.textContent='{{ __('Copied') }}'" class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Copy wall link') }}</button>
            <form method="POST" action="{{ route('admin.led.regenerate', $display) }}" onsubmit="return confirm('{{ __('Create a new link? Screens on the current link will stop.') }}')">
                @csrf
                <button class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">{{ __('New link') }}</button>
            </form>
        </div>
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
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">{{ __('Show now') }}</h3>
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
    <form id="settingsForm" class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm" onsubmit="led.saveSettings(event)">
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
        <div class="col-span-full"><button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">{{ __('Save settings') }}</button> <span id="savedNote" class="text-green-600 hidden">{{ __('Saved') }}</span></div>
    </form>
</div>
@endsection

@push('scripts')
@include('backend.pages.auction.partials.echo-init')
<script>
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

    window.pushChannel?.('led.{{ $display->id }}')?.listen('.led.changed', load);
    window.pushChannel?.('match.{{ $match->id }}')?.listen('.score.updated', load);
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
            };
            post('/settings', body).then(j => { if (j.ok) { const n = document.getElementById('savedNote'); n.classList.remove('hidden'); setTimeout(() => n.classList.add('hidden'), 2000); } });
        },
    };
})();
</script>
@endpush
