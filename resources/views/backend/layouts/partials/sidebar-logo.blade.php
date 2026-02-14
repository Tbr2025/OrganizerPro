<aside :class="sidebarToggle ? 'translate-x-0 lg:w-[80px] app-sidebar-minified' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-10 flex h-screen w-[280px] flex-col overflow-y-hidden border-r border-gray-100 shadow-sm transition-all duration-300 ease-in-out {{ config('settings.sidebar_bg_lite') ? '' : 'bg-white' }} dark:border-gray-800/50 dark:bg-dark-sidebar lg:static lg:translate-x-0"
    id="appSidebar" x-data="{
        isHovered: false,
        init() {
            this.updateBg();
            const observer = new MutationObserver(() => this.updateBg());
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

            // Check if sidebarToggle value is present in localStorage and use it
            if (localStorage.getItem('sidebarToggle')) {
                sidebarToggle = JSON.parse(localStorage.getItem('sidebarToggle'));
            }
        },
        updateBg() {
            const htmlHasDark = document.documentElement.classList.contains('dark');
            const liteBg = '{{ config('settings.sidebar_bg_lite') }}';
            const darkBg = '{{ config('settings.sidebar_bg_dark') }}';
            this.$el.style.backgroundColor = htmlHasDark ? darkBg : liteBg;
        }
    }" x-init="init()"
    @mouseenter="if(sidebarToggle) { isHovered = true; $el.classList.add('lg:w-[280px]'); $el.classList.remove('lg:w-[80px]', 'app-sidebar-minified'); }"
    @mouseleave="if(sidebarToggle) { isHovered = false; $el.classList.add('lg:w-[80px]', 'app-sidebar-minified'); $el.classList.remove('lg:w-[280px]'); }">
    <!-- Sidebar Header -->
    <div :class="sidebarToggle && !isHovered ? 'justify-center' : 'justify-between'"
        class="justify-center flex items-center gap-2 sidebar-header py-4 px-5 h-[72px] border-b border-gray-100 dark:border-gray-800/50 transition-all duration-300">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center">
            <span class="logo transition-all duration-300"
                :class="sidebarToggle && !isHovered ? 'hidden opacity-0 scale-95' : 'opacity-100 scale-100'">
                <img class="dark:hidden max-h-[36px]"
                    src="{{ config('settings.site_logo_lite') ?? asset('images/logo/lara-dashboard.png') }}"
                    alt="{{ config('app.name') }}" />
                <img class="hidden dark:block max-h-[36px]"
                    src="{{ config('settings.site_logo_dark') ?? '/images/logo/lara-dashboard-dark.png' }}"
                    alt="{{ config('app.name') }}" />
            </span>
            <img class="logo-icon w-10 transition-all duration-300"
                :class="sidebarToggle && !isHovered ? 'lg:block opacity-100 scale-100' : 'hidden opacity-0 scale-95'"
                src="{{ config('settings.site_icon') ?? '/images/logo/icon.png' }}" alt="{{ config('app.name') }}" />
        </a>
    </div>
    <!-- End Sidebar Header -->

    <div class="flex flex-col overflow-y-auto duration-300 ease-linear custom-scrollbar py-4">
        @include('backend.layouts.partials.sidebar-menu')
    </div>
</aside>
<!-- End Sidebar -->
