@php
    $menuService = app(\App\Services\MenuService\AdminMenuService::class);
    $menuGroups = $menuService->getMenu();
@endphp

<nav
    x-data="{
        isDark: document.documentElement.classList.contains('dark'),
        textColor: '',
        init() {
            this.updateColor();
            const observer = new MutationObserver(() => this.updateColor());
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        updateColor() {
            this.isDark = document.documentElement.classList.contains('dark');
        },
        openDrawer(drawerId) {
            if (typeof window.openDrawer === 'function') {
                window.openDrawer(drawerId);
            }
        }
    }"
    x-init="init()"
    class="transition-all duration-300 ease-in-out px-3"
>
    @foreach($menuGroups as $groupName => $groupItems)
        {!! ld_apply_filters('sidebar_menu_group_before_' . Str::slug($groupName), '') !!}
        <div class="mb-2">
            {!! ld_apply_filters('sidebar_menu_group_heading_before_' . Str::slug($groupName), '') !!}
            <h3 class="menu-group-heading mb-3 text-[11px] uppercase tracking-wider leading-[18px] text-gray-400 font-semibold dark:text-magenta-400 px-3">
                {{ __($groupName) }}
            </h3>
            {!! ld_apply_filters('sidebar_menu_group_heading_after_' . Str::slug($groupName), '') !!}
            <ul class="flex flex-col mb-4 space-y-0.5">
                {!! ld_apply_filters('sidebar_menu_before_all_' . Str::slug($groupName), '') !!}
                {!! $menuService->render($groupItems) !!}
                {!! ld_apply_filters('sidebar_menu_after_all_' . Str::slug($groupName), '') !!}
            </ul>
        </div>
        {!! ld_apply_filters('sidebar_menu_group_after_' . Str::slug($groupName), '') !!}
    @endforeach
</nav>

<script>
    // Ensure drawer triggers work in the sidebar
    document.addEventListener('DOMContentLoaded', function() {
        // Handle drawer trigger clicks
        document.querySelectorAll('[data-drawer-trigger]').forEach(function(element) {
            element.addEventListener('click', function(e) {
                const drawerId = this.getAttribute('data-drawer-trigger');
                if (drawerId) {
                    e.preventDefault();
                    if (typeof window.openDrawer === 'function') {
                        window.openDrawer(drawerId);
                    } else {
                        // Fallback if the global function isn't available yet
                        window.dispatchEvent(new CustomEvent('open-drawer-' + drawerId));
                    }
                    return false;
                }
            });
        });
    });
</script>
