@props([
    // Filter definitions from PlayerFilters::definitions()
    'definitions' => [],
    // Heading count, e.g. "263 registered players"
    'total' => 0,
    'noun' => 'players',
])

@php
    // One row per filter, in this order. Only the handful worth one-click access
    // live here — everything else is in the full filter panel.
    $rows = ['player_type', 'wk', 'batting', 'bowling', 'visa_status', 'transportation', 'travel_plan'];

    // Palettes are limited to colours already compiled into the stylesheet.
    $palette = [
        'blue' => ['on' => 'bg-blue-600 text-white ring-1 ring-blue-700', 'off' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/30', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-blue-200 text-blue-900 dark:bg-blue-700 dark:text-blue-50'],
        'orange' => ['on' => 'bg-orange-600 text-white ring-1 ring-orange-700', 'off' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 hover:bg-orange-100 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-400/30', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-orange-200 text-orange-900 dark:bg-orange-700 dark:text-orange-50'],
        'indigo' => ['on' => 'bg-indigo-600 text-white ring-1 ring-indigo-700', 'off' => 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-indigo-200 text-indigo-900 dark:bg-indigo-700 dark:text-indigo-50'],
        'green' => ['on' => 'bg-green-600 text-white ring-1 ring-green-700', 'off' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 hover:bg-green-100 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/30', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-green-200 text-green-900 dark:bg-green-700 dark:text-green-50'],
        'purple' => ['on' => 'bg-purple-600 text-white ring-1 ring-purple-700', 'off' => 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20 hover:bg-purple-100 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-400/30', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-purple-200 text-purple-900 dark:bg-purple-700 dark:text-purple-50'],
        'gray' => ['on' => 'bg-gray-700 text-white ring-1 ring-gray-800', 'off' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-400/40 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-500/40', 'badgeOn' => 'bg-white/25 text-white', 'badgeOff' => 'bg-gray-300 text-gray-800 dark:bg-gray-600 dark:text-gray-100'],
    ];

    $rowColour = [
        'player_type' => 'blue',
        'wk' => 'orange',
        'batting' => 'indigo',
        'bowling' => 'green',
        'visa_status' => 'purple',
        'transportation' => 'gray',
        'travel_plan' => 'gray',
    ];

    // Build the rows up front so empty ones can be skipped entirely.
    $builtRows = [];
    foreach ($rows as $key) {
        if (! isset($definitions[$key])) {
            continue;
        }

        $def = $definitions[$key];
        $items = [];

        foreach ($def['options'] ?? [] as $value => $optionLabel) {
            if ($value === 'none') {
                continue;
            }

            // Option labels arrive as "Right-hand Bat (182)" — split the count out so
            // it can sit in its own badge.
            preg_match('/^(.*?)\s*\((\d+)\)$/', $optionLabel, $m);
            $text = $m[1] ?? $optionLabel;
            $count = isset($m[2]) ? (int) $m[2] : null;

            // A bare "Yes" means nothing on its own, so booleans carry their own
            // self-describing chip label.
            $text = $def['chip_labels'][$value] ?? $text;

            if ($count === 0 && ! filter_is_active($key, $value)) {
                continue;
            }

            $items[] = ['value' => $value, 'label' => $text, 'count' => $count];
        }

        if (! empty($items)) {
            $builtRows[] = [
                'label' => $def['label'],
                'param' => $key,
                'items' => $items,
                'colour' => $rowColour[$key] ?? 'gray',
            ];
        }
    }

    $allParams = array_map(fn ($r) => $r['param'], $builtRows);
@endphp

@if (! empty($builtRows))
<div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 mb-4">
    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-800">
        <iconify-icon icon="lucide:zap" width="15" class="text-amber-500"></iconify-icon>
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {{ __('Quick Filters') }}
        </h3>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ number_format($total) }} {{ $noun }}</span>
        @if (request()->hasAny($allParams))
            <a href="{{ filter_url([], $allParams) }}"
               class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400">
                <iconify-icon icon="lucide:rotate-ccw" width="12"></iconify-icon>
                {{ __('Clear these') }}
            </a>
        @endif
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($builtRows as $row)
            @php $c = $palette[$row['colour']]; @endphp
            <div class="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-start sm:gap-3">

                <div class="flex items-center gap-1.5 shrink-0 sm:w-32 sm:pt-1">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ $row['label'] }}
                    </span>
                    @if (request()->filled($row['param']))
                        <a href="{{ filter_url([], [$row['param']]) }}" title="{{ __('Clear') }}"
                           class="text-gray-300 hover:text-red-600 dark:text-gray-600">
                            <iconify-icon icon="lucide:x-circle" width="12"></iconify-icon>
                        </a>
                    @endif
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach ($row['items'] as $item)
                        @php $isActive = filter_is_active($row['param'], $item['value']); @endphp
                        <a href="{{ toggle_filter_url($row['param'], $item['value']) }}"
                           title="{{ $isActive ? __('Click to remove this filter') : __('Click to filter') }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap transition {{ $isActive ? $c['on'] : $c['off'] }}">
                            {{ $item['label'] }}
                            @if ($item['count'] !== null)
                                <span class="{{ $isActive ? $c['badgeOn'] : $c['badgeOff'] }} px-1.5 rounded-full text-[10px] font-bold leading-4">{{ $item['count'] }}</span>
                            @endif
                            @if ($isActive)
                                <iconify-icon icon="lucide:x" width="11"></iconify-icon>
                            @endif
                        </a>
                    @endforeach
                </div>

            </div>
        @endforeach
    </div>
</div>
@endif
