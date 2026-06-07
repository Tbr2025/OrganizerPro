@php
    $breadcrumbs = $breadcrumbs ?? [];

    // Detect format: indexed array of ['name'=>..., 'route'=>...] vs associative ['title'=>..., 'items'=>...]
    $isIndexedArray = !empty($breadcrumbs) && isset($breadcrumbs[0]);

    if ($isIndexedArray) {
        // Filter out null entries
        $breadcrumbs = array_filter($breadcrumbs);
        // Last item becomes the title/current page
        $lastItem = end($breadcrumbs);
        $parsedTitle = $lastItem['name'] ?? $lastItem['label'] ?? '';
        // All items except last become navigation items
        $parsedItems = array_slice($breadcrumbs, 0, -1);
        // Normalize keys: name->label, route->url
        $parsedItems = array_map(function ($item) {
            return [
                'label' => $item['label'] ?? $item['name'] ?? '',
                'url' => $item['url'] ?? $item['route'] ?? '#',
            ];
        }, $parsedItems);
        // Override breadcrumbs to associative format
        $breadcrumbs = [
            'title' => $parsedTitle,
            'items' => $parsedItems,
            'show_home' => false, // First item usually is Dashboard, no need for extra Home
        ];
    }
@endphp

@props([
    'disabled' => $breadcrumbs['disabled'] ?? false,
    'title' => $breadcrumbs['title'] ?? '',
    'items' => $breadcrumbs['items'] ?? [],
    'show_home' => $breadcrumbs['show_home'] ?? true,
    'show_current' => $breadcrumbs['show_current'] ?? true,
    'title_after' => $breadcrumbs['title_after'] ?? '',
    'show_messages_after' => $breadcrumbs['show_messages_after'] ?? true,
])

@if (!$disabled)
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-3">
        @if(!empty($title))
        <h2 class="text-xl font-semibold text-gray-700 dark:text-white/90 flex justify-center items-center gap-2">
            {!! $title_before ?? '' !!}
            {{ __($title) }}

            {!! $title_after !!}
        </h2>
        @endif

        @if(count($items) || ($show_home || $show_current))
        <nav>
            <ol class="flex items-center gap-1.5 pe-2">
                @if($show_home)
                    <li>
                        <a
                            class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="{{ route('admin.dashboard') }}"
                        >
                            {{ __("Home") }}
                            <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                        </a>
                    </li>
                @endif

                @foreach($items as $item)
                    <li>
                        <a
                            class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="{{ $item['url'] }}"
                        >
                            {{ __($item['label']) }}
                            <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                        </a>
                    </li>
                @endforeach

                @if($show_current)
                    <li class="text-sm text-gray-700 dark:text-white/90">
                        {{ __($title) }}
                    </li>
                @endif
            </ol>
        </nav>
        @endif
    </div>

    <a href="javascript:history.back()"
       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
        <iconify-icon icon="lucide:arrow-left" class="text-base"></iconify-icon>
        {{ __('Go Back') }}
    </a>
</div>
@endif

@if($show_messages_after)
    <x-messages />
@endif
