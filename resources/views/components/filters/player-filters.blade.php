@props([
    // Filter definitions from PlayerFilters::definitions()
    'definitions' => [],
    // Params the surrounding page owns (search, status, sort…) — kept as hidden
    // inputs so applying a filter never loses the current tab or sort order.
    'keep' => [],
    // Where "Reset all" points. Defaults to the page with every filter dropped.
    'resetUrl' => null,
    'action' => null,
])

@php
    $groups = \App\Support\PlayerFilters::grouped($definitions);
    $chips = \App\Support\PlayerFilters::activeChips(request(), $definitions);
    $paramNames = \App\Support\PlayerFilters::parameterNames($definitions);
    $activeCount = count($chips);
    $resetHref = $resetUrl ?? filter_url([], $paramNames);
@endphp

@if (!empty($definitions))
<div x-data="{ open: {{ $activeCount > 0 ? 'true' : 'false' }} }"
     class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 mb-4">

    {{-- Header / toggle --}}
    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-800">
        <button type="button" @click="open = !open"
                class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
            <iconify-icon icon="lucide:sliders-horizontal" width="16"></iconify-icon>
            {{ __('Filters') }}
            @if ($activeCount > 0)
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-600 text-white">{{ $activeCount }}</span>
            @endif
            <iconify-icon ::icon="open ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" class="text-gray-400"></iconify-icon>
        </button>

        @if ($activeCount > 0)
            <a href="{{ $resetHref }}"
               class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400">
                <iconify-icon icon="lucide:rotate-ccw" width="13"></iconify-icon>
                {{ __('Reset all') }}
            </a>
        @endif
    </div>

    {{-- Active filter chips — each one removes just itself --}}
    @if ($activeCount > 0)
        <div class="flex flex-wrap gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-800">
            @foreach ($chips as $chip)
                <a href="{{ filter_url([], $chip['params']) }}"
                   class="group inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/15 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/20"
                   title="{{ __('Remove this filter') }}">
                    <span class="text-indigo-400 dark:text-indigo-500">{{ $chip['label'] }}:</span>
                    <span>{{ $chip['value'] }}</span>
                    <iconify-icon icon="lucide:x" width="12"
                        class="text-indigo-400 group-hover:text-red-600"></iconify-icon>
                </a>
            @endforeach
        </div>
    @endif

    {{-- The form --}}
    <div x-show="open" x-cloak class="p-4">
        <form method="GET" action="{{ $action ?? request()->url() }}">
            @foreach ($keep as $name => $value)
                @if ($value !== null && $value !== '')
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach

            @foreach ($groups as $section => $sectionFilters)
                <div class="mb-4 last:mb-0">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">
                        {{ $section }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach ($sectionFilters as $key => $def)
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    {{ $def['label'] }}
                                </label>

                                @if ($def['type'] === 'range')
                                    <div class="flex items-center gap-1">
                                        <input type="number" name="{{ $key }}_min" value="{{ request($key . '_min') }}"
                                               placeholder="{{ __('Min') }}" min="0"
                                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        <span class="text-gray-400 text-xs">–</span>
                                        <input type="number" name="{{ $key }}_max" value="{{ request($key . '_max') }}"
                                               placeholder="{{ __('Max') }}" min="0"
                                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    </div>
                                @elseif ($def['type'] === 'text')
                                    <input type="text" name="{{ $key }}" value="{{ request($key) }}"
                                           placeholder="{{ $def['placeholder'] ?? '' }}"
                                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                @else
                                    <select name="{{ $key }}"
                                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        <option value="">{{ __('Any') }}</option>
                                        @foreach ($def['options'] ?? [] as $value => $label)
                                            <option value="{{ $value }}" @selected((string) request($key) === (string) $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                    <iconify-icon icon="lucide:filter" width="14"></iconify-icon>
                    {{ __('Apply filters') }}
                </button>
                <a href="{{ $resetHref }}"
                   class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    {{ __('Reset') }}
                </a>
                {{ $slot }}
            </div>
        </form>
    </div>
</div>
@endif
