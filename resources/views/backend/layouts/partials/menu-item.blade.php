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
        <button class="menu-item group w-full text-left {{ $isActive }}" type="button" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.menu-item-arrow').classList.toggle('rotate-180')">
            @if (!empty($item->icon))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-white/[0.08] transition-colors duration-150">
                    <iconify-icon icon="{{ $item->icon }}" class="menu-item-icon text-gray-500 dark:text-gray-400 transition-colors duration-150" width="17" height="17"></iconify-icon>
                </span>
            @elseif (!empty($item->iconClass))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-white/[0.08]">
                    <iconify-icon icon="lucide:circle" class="menu-item-icon text-gray-500 dark:text-gray-400" width="17" height="17"></iconify-icon>
                </span>
            @endif
            <span class="menu-item-text">{!! $item->label !!}</span>
            <iconify-icon icon="lucide:chevron-down" class="menu-item-arrow transition-transform duration-150 {{ $rotateClass }} w-4 h-4 text-gray-400 dark:text-gray-500"></iconify-icon>
        </button>
        <ul id="{{ $submenuId }}" class="submenu mt-0.5 ml-4 pl-3 border-l border-gray-200 dark:border-gray-700/60 space-y-px overflow-hidden transition-all duration-150 {{ $showSubmenu ? '' : 'hidden' }}">
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
        <a href="{{ $item->route ?? '#' }}" class="menu-item group {{ $isActive }}" {!! $target !!}>
            @if (!empty($item->icon))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-white/[0.08] transition-colors duration-150">
                    <iconify-icon icon="{{ $item->icon }}" class="menu-item-icon text-gray-500 dark:text-gray-400 transition-colors duration-150" width="17" height="17"></iconify-icon>
                </span>
            @elseif (!empty($item->iconClass))
                <span class="menu-item-icon-wrapper flex items-center justify-center w-8 h-8 rounded-md bg-gray-100 dark:bg-white/[0.08]">
                    <iconify-icon icon="lucide:circle" class="menu-item-icon text-gray-500 dark:text-gray-400" width="17" height="17"></iconify-icon>
                </span>
            @endif
            <span class="menu-item-text">{!! $item->label !!}</span>
        </a>
    </li>
@endif

@if(isset($item->id))
    {!! ld_apply_filters('sidebar_menu_item_after_' . strtolower($item->id), '') !!}
@endif
