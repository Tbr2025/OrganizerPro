{{--
    Template preset picker: one tickbox per preset, grouped by poster type, with a
    picture of the design. Ticked ones are copied into the tournament.

    @param \Illuminate\Support\Collection $presets   tournament_templates rows with no tournament
    @param string|null                    $orgSelect  CSS selector of an organization <select>; presets of
                                                      other organizations are hidden (and not posted)
                                                      while it points elsewhere — the create form, for Super Admin
    @param string|null                    $typeSelect CSS selector of the tournament type <select>; auction poster
                                                      presets are hidden unless it says "auction"

    Premium presets carry their price; whenever the ticks change the picker fires
    `presets-changed` on document with {premium: <credits for the ticked premium presets>}.
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
                        <label data-org="{{ $preset->organization_id ?? '' }}" data-offered="{{ $preset->offered_to ? implode(',', $preset->offered_to) : '' }}"
                               data-auction="{{ \App\Models\TournamentTemplate::isAuctionType($preset->type) ? 1 : 0 }}"
                               class="preset-pick relative block cursor-pointer rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/30">
                            <input type="checkbox" name="presets[]" value="{{ $preset->id }}" data-price="{{ (int) ($preset->effective_price ?? ($preset->isPremium() ? $preset->price : 0)) }}" class="absolute top-1.5 right-1.5 z-10" @checked($preset->is_active)>
                            <img src="{{ route('admin.template-library.thumb', [$preset, 'v' => $preset->design_version]) }}" alt="" loading="lazy"
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
                                    @if($preset->isPremium())
                                        @php $pays = auth()->user()?->hasRole('Superadmin') ? (int) $preset->price : (int) ($preset->effective_price ?? $preset->price); @endphp
                                        <span class="text-[10px] px-1.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Premium') }} ·
                                            @if($pays < $preset->price)<s class="opacity-60">{{ number_format($preset->price) }}</s>@endif
                                            {{ $pays === 0 ? __('free for you') : app(\App\Services\Credits\CreditService::class)->format($pays) }}</span>
                                    @else
                                        <span class="text-[10px] px-1.5 rounded bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300" title="{{ __('Only its background can be changed') }}">{{ __('Free') }}</span>
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
    const typeSelect = @json($typeSelect ?? null) ? document.querySelector(@json($typeSelect ?? '')) : null;
    const items = () => [...root.querySelectorAll('.preset-pick')];
    const visible = () => items().filter(l => l.style.display !== 'none');

    function count() {
        const ticked = visible().map(l => l.querySelector('input')).filter(i => i.checked);
        const premium = ticked.reduce((sum, i) => sum + Number(i.dataset.price || 0), 0);
        root.querySelector('[data-pick-count]').textContent = ticked.length + ' {{ __('ticked') }}'
            + (premium ? ' · {{ __('premium') }} ' + premium.toLocaleString() + ' {{ app(\App\Services\Credits\CreditService::class)->term() }}' : '');
        document.dispatchEvent(new CustomEvent('presets-changed', { detail: { premium } }));
    }
    function filter() {
        const org = orgSelect ? String(orgSelect.value || '') : null;
        const auction = typeSelect ? typeSelect.value === 'auction' : true;
        items().forEach(l => {
            const offered = l.dataset.offered ? l.dataset.offered.split(',') : null;
            const forOrg = org === null || (l.dataset.org === '' ? (! offered || ! org || offered.includes(org)) : l.dataset.org === org);
            const show = forOrg && (auction || l.dataset.auction !== '1');
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
    if (typeSelect) typeSelect.addEventListener('change', filter);
    filter();
})();
</script>
@endif
