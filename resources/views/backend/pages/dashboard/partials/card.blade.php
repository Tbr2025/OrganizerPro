@php
    $enable_full_div_click = $enable_full_div_click ?? true;
    $gradient = $gradient ?? false;
@endphp

<div class="relative overflow-hidden rounded-2xl {{ $gradient ? 'gradient-card-purple-pink' : 'bg-white dark:bg-dark-card border border-gray-200 dark:border-gray-700/50' }} px-5 pb-5 pt-5 shadow-lg dark:shadow-black/10 {{ $enable_full_div_click ? 'cursor-pointer hover:shadow-xl hover:scale-[1.02] transition-all duration-300' : '' }}"
    @if($enable_full_div_click)
        onclick="window.location.href='{{ $url ?? '#' }}'"
    @endif
>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium {{ $gradient ? 'text-white/80' : 'text-gray-500 dark:text-gray-400' }} mb-1">{{ $label }}</p>
            <p class="text-2xl font-bold {{ $gradient ? 'text-white' : 'text-gray-800 dark:text-white' }}">{!! $value ?? 0 !!}</p>
        </div>
        <div class="flex h-14 w-14 items-center justify-center rounded-xl {{ $gradient ? 'bg-white/20' : '' }}" style="{{ !$gradient ? 'background: ' . ($icon_bg ?? '#6366F1') . '20;' : '' }}">
            @if(!empty($icon))
                <iconify-icon icon="{{ $icon }}" class="size-7 {{ $gradient ? 'text-white' : '' }}" style="{{ !$gradient ? 'color: ' . ($icon_bg ?? '#6366F1') . ';' : '' }}" height="28" width="28"></iconify-icon>
            @elseif(!empty($icon_svg))
                <img src="{{ $icon_svg }}" alt="" class="size-7">
            @else
                <svg class="size-7 {{ $gradient ? 'text-white' : '' }}" style="{{ !$gradient ? 'color: ' . ($icon_bg ?? '#6366F1') . ';' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
            @endif
        </div>
    </div>

    @if(!$gradient)
    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/50">
        <a href="{{ $url ?? '#' }}" class="inline-flex items-center text-sm font-medium text-cyan-600 hover:text-cyan-500 dark:text-cyan-400 dark:hover:text-cyan-300 transition-colors">
            View all
            <iconify-icon icon="heroicons:arrow-right" class="ml-1" width="16" height="16"></iconify-icon>
        </a>
    </div>
    @endif
</div>
