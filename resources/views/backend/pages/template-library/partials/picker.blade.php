{{--
    Template preset picker: one tickbox per preset, grouped by poster type, with a
    picture of the design. Ticked ones are copied into the tournament.

    @param \Illuminate\Support\Collection $presets   tournament_templates rows with no tournament
    @param string|null                    $orgSelect  CSS selector of an organization <select>; presets of
                                                      other organizations are hidden (and not posted)
                                                      while it points elsewhere — the create form, for Super Admin
--}}
@php
    $pickerId = 'presetPicker' . uniqid();
    $byType = $presets->groupBy('type');
@endphp

<div id="{{ $pickerId }}" class="space-y-3">
    @if($presets->isEmpty())
        <p class="text-xs text-gray-500">
            {{ __('No template presets yet.') }}
            @can('template.presets')
                <a href="{{ route('admin.template-library.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ __('Create presets') }}</a>
                {{ __('and they will be offered here.') }}
            @endcan
        </p>
    @else
        <div class="flex items-center gap-3 text-xs">
            <button type="button" data-pick="all" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ __('Tick all') }}</button>
            <button type="button" data-pick="none" class="text-gray-500 hover:text-gray-700 font-medium">{{ __('Untick all') }}</button>
            <span class="text-gray-400" data-pick-count></span>
        </div>

        @foreach($byType as $type => $list)
            <div data-type-group>
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1.5">{{ \App\Models\TournamentTemplate::getTypeDisplay($type) }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($list as $preset)
                        <label data-org="{{ $preset->organization_id ?? '' }}"
                               class="preset-pick relative block cursor-pointer rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/30">
                            <input type="checkbox" name="presets[]" value="{{ $preset->id }}" class="absolute top-1.5 right-1.5 z-10" @checked($preset->is_active)>
                            <img src="{{ route('admin.template-library.thumb', $preset) }}" alt="" loading="lazy"
                                 class="w-full bg-gray-100 dark:bg-gray-800" style="aspect-ratio:4/5;object-fit:contain"
                                 onerror="this.style.visibility='hidden'">
                            <span class="block p-1.5">
                                <span class="block text-xs font-medium text-gray-900 dark:text-white truncate">{{ $preset->name }}</span>
                                <span class="flex flex-wrap gap-1 mt-0.5">
                                    <span class="text-[10px] px-1.5 rounded {{ $preset->organization_id ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $preset->organization_id ? ($preset->organization?->name ?? __('Organization')) : __('Global') }}
                                    </span>
                                    @if($preset->is_default)
                                        <span class="text-[10px] px-1.5 rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Default') }}</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>

@if($presets->isNotEmpty())
<script>
(function () {
    const root = document.getElementById(@json($pickerId));
    const orgSelect = @json($orgSelect ?? null) ? document.querySelector(@json($orgSelect ?? '')) : null;
    const items = () => [...root.querySelectorAll('.preset-pick')];
    const visible = () => items().filter(l => l.style.display !== 'none');

    function count() {
        const n = visible().filter(l => l.querySelector('input').checked).length;
        root.querySelector('[data-pick-count]').textContent = n + ' {{ __('ticked') }}';
    }
    function filter() {
        const org = orgSelect ? String(orgSelect.value || '') : null;
        items().forEach(l => {
            const show = org === null || l.dataset.org === '' || l.dataset.org === org;
            l.style.display = show ? '' : 'none';
            l.querySelector('input').disabled = !show; // a hidden preset is never posted
        });
        root.querySelectorAll('[data-type-group]').forEach(g => {
            g.style.display = [...g.querySelectorAll('.preset-pick')].some(l => l.style.display !== 'none') ? '' : 'none';
        });
        count();
    }
    root.querySelectorAll('[data-pick]').forEach(b => b.addEventListener('click', () => {
        visible().forEach(l => l.querySelector('input').checked = b.dataset.pick === 'all');
        count();
    }));
    root.addEventListener('change', count);
    if (orgSelect) orgSelect.addEventListener('change', filter);
    filter();
})();
</script>
@endif
