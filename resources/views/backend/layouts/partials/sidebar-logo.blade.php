<aside
    x-data="{
        isHovered: false,
        sidebarBg: '',
        init() {
            this.updateBg();
            const observer = new MutationObserver(() => this.updateBg());
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        updateBg() {
            const htmlHasDark = document.documentElement.classList.contains('dark');
            const liteBg = '{{ config('settings.sidebar_bg_lite', '#ffffff') }}';
            const darkBg = '{{ config('settings.sidebar_bg_dark', '#1C2434') }}';
            this.sidebarBg = htmlHasDark ? darkBg : liteBg;
        },
        get isMinified() {
            return sidebarToggle && !this.isHovered;
        }
    }"
    x-init="init()"
    :style="{ backgroundColor: sidebarBg }"
    :class="{
        'translate-x-0': sidebarToggle,
        '-translate-x-full lg:translate-x-0': !sidebarToggle,
        'lg:w-[80px]': sidebarToggle && !isHovered && !teamManagerLayout,
        'lg:w-[280px]': !sidebarToggle || isHovered || teamManagerLayout,
        'app-sidebar-minified': sidebarToggle && !isHovered && !teamManagerLayout
    }"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
    class="sidebar fixed left-0 top-0 z-10 flex h-screen w-[280px] flex-col overflow-y-hidden border-r border-gray-200 dark:border-gray-800/50 shadow-sm bg-white dark:bg-dark-sidebar lg:static"
    style="transition: width 200ms ease-out, transform 200ms ease-out;"
    id="appSidebar">

    <!-- Sidebar Header -->
    <div
        :class="isMinified ? 'justify-center px-2' : 'justify-between px-5'"
        class="flex items-center gap-2 sidebar-header py-4 h-[72px] border-b border-gray-200 dark:border-gray-800/50"
        style="transition: padding 200ms ease-out;">
        <a href="{{ route('home') }}" class="flex items-center min-w-0 overflow-hidden max-w-full flex-1">
            <!-- Full Logo (shown when expanded) -->
            <span
                class="logo flex-shrink-0 overflow-hidden"
                :class="isMinified ? 'hidden' : 'block'"
                style="height: 36px; max-width: 180px;">
                <img
                    class="dark:hidden h-[36px] max-w-[180px] object-contain"
                    src="{{ config('settings.site_logo_lite') ?? asset('images/logo/lara-dashboard.png') }}"
                    alt="{{ config('app.name') }}"
                    loading="eager" />
                <img
                    class="hidden dark:block h-[36px] max-w-[180px] object-contain"
                    style="filter: brightness(0) invert(1);"
                    src="{{ config('settings.site_logo_dark') ?? asset('images/logo/lara-dashboard-dark.png') }}"
                    alt="{{ config('app.name') }}"
                    loading="eager" />
            </span>
            <!-- Icon Logo (shown when minified) -->
            <img
                class="logo-icon w-10 h-10 object-contain flex-shrink-0"
                :class="isMinified ? 'block' : 'hidden'"
                src="{{ config('settings.site_icon') ?? asset('images/logo/icon.png') }}"
                alt="{{ config('app.name') }}"
                loading="eager" />
        </a>
        <!-- Mobile close button (hidden on desktop) -->
        <button
            @click="sidebarToggle = false"
            class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 flex-shrink-0"
            title="Close menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <!-- End Sidebar Header -->

    <div class="flex flex-col overflow-y-auto custom-scrollbar py-4 flex-1">
        @include('backend.layouts.partials.sidebar-menu')
    </div>
</aside>
<!-- End Sidebar -->
