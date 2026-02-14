@php
    /** @var \App\Services\MenuService\AdminMenuItem $item */
@endphp

@if (isset($item->htmlData))
    <div class="menu-item-html" style="{!! $item->itemStyles !!}">
        {!! $item->htmlData !!}
    </div>
@elseif (!empty($item->children))
    @php
        $submenuId = $item->id ?? \Str::slug($item->label) . '-submenu';
        $isActive = $item->active ? 'menu-item-active' : '';
        $showSubmenu = app(\App\Services\MenuService\AdminMenuService::class)->shouldExpandSubmenu($item);
        $rotateClass = $showSubmenu ? 'rotate-180' : '';
    @endphp

    <li class="menu-item-{{ $item->id }}" style="{!! $item->itemStyles !!}">
        <button :style="`color: ${textColor}`" class="menu-item group w-full text-left {{ $isActive }}" type="button" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.menu-item-arrow').classList.toggle('rotate-180')">
            @if (!empty($item->icon))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 dark:bg-gray-800/50 group-hover:bg-cyan-50 dark:group-hover:bg-cyan-500/10 transition-colors duration-200">
                    <iconify-icon icon="{{ $item->icon }}" class="menu-item-icon text-gray-500 dark:text-cyan-400 group-hover:text-cyan-500 transition-colors duration-200" width="18" height="18"></iconify-icon>
                </span>
            @elseif (!empty($item->iconClass))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                    <iconify-icon icon="lucide:circle" class="menu-item-icon dark:text-cyan-400" width="18" height="18"></iconify-icon>
                </span>
            @endif
            <span class="menu-item-text font-medium">{!! $item->label !!}</span>
            <iconify-icon icon="lucide:chevron-down" class="menu-item-arrow transition-transform duration-200 {{ $rotateClass }} w-4 h-4 text-gray-400"></iconify-icon>
        </button>
        <ul id="{{ $submenuId }}" class="submenu mt-1 ml-[18px] pl-4 border-l-2 border-gray-100 dark:border-gray-700 space-y-0.5 overflow-hidden transition-all duration-200 {{ $showSubmenu ? '' : 'hidden' }}">
            @foreach($item->children as $child)
                @include('backend.layouts.partials.menu-item', ['item' => $child])
            @endforeach
        </ul>
    </li>
@else
    @php
        $isActive = $item->active ? 'menu-item-active' : 'menu-item-inactive';
        $target = !empty($item->target) ? ' target="' . e($item->target) . '"' : '';
    @endphp

    <li class="menu-item-{{ $item->id }}" style="{!! $item->itemStyles !!}">
        <a :style="`color: ${textColor}`" href="{{ $item->route ?? '#' }}" class="menu-item group {{ $isActive }}" {!! $target !!}>
            @if (!empty($item->icon))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 dark:bg-gray-800/50 group-hover:bg-cyan-50 dark:group-hover:bg-cyan-500/10 transition-colors duration-200 {{ $item->active ? '!bg-cyan-50 dark:!bg-cyan-500/15' : '' }}">
                    <iconify-icon icon="{{ $item->icon }}" class="menu-item-icon text-gray-500 dark:text-cyan-400 group-hover:text-cyan-500 transition-colors duration-200 {{ $item->active ? '!text-cyan-500 dark:!text-cyan-400' : '' }}" width="18" height="18"></iconify-icon>
                </span>
            @elseif (!empty($item->iconClass))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-9 h-9 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                    <iconify-icon icon="lucide:circle" class="menu-item-icon dark:text-cyan-400" width="18" height="18"></iconify-icon>
                </span>
            @endif
            <span class="menu-item-text font-medium">{!! $item->label !!}</span>
        </a>
    </li>
@endif

@if(isset($item->id))
    {!! ld_apply_filters('sidebar_menu_item_after_' . strtolower($item->id), '') !!}
@endif
