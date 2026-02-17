<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ config('settings.site_favicon') ?? asset('favicon.ico') }}" type="image/x-icon">

    @include('backend.layouts.partials.theme-colors')
    @yield('before_vite_build')
    @stack('before-alpine')

    {{-- Alpine.js x-cloak support - must be before Alpine loads --}}
    <style>[x-cloak] { display: none !important; }</style>

    @viteReactRefresh
    @vite(['resources/js/app.js', 'resources/css/app.css'], 'build')
    @stack('styles')
    @yield('before_head')

    @if (!empty(config('settings.global_custom_css')))
    <style>
        {!! config('settings.global_custom_css') !!}
    </style>
    @endif

    @include('backend.layouts.partials.integration-scripts')
    
    @php echo ld_apply_filters('admin_head', ''); @endphp
</head>

<body x-data="{
    page: 'ecommerce',
    loaded: true,
    darkMode: true,
    stickyMenu: false,
    sidebarToggle: $persist(false),
    scrollTop: false
}"
x-init="
    darkMode = JSON.parse(localStorage.getItem('darkMode')) ?? true;
    $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)));
    $watch('sidebarToggle', value => localStorage.setItem('sidebarToggle', JSON.stringify(value)))
"
:class="{ 'dark bg-dark-bg': darkMode === true }">
    <!-- Page Transition Overlay -->
    <div
        x-show="loaded"
        x-transition:leave="transition-opacity ease-out duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-init="setTimeout(() => loaded = false, 100)"
        class="fixed inset-0 z-999999 bg-white dark:bg-dark-bg pointer-events-none">
    </div>
    <!-- End Page Transition -->
    <!-- Page Wrapper -->
    <div class="flex h-screen overflow-hidden">
        @include('backend.layouts.partials.sidebar-logo')

        <!-- Content Area -->
        <div class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto bg-white dark:bg-dark-surface">
            <!-- Small Device Overlay -->
            <div @click="sidebarToggle = false" :class="sidebarToggle ? 'block lg:hidden' : 'hidden'"
                class="fixed w-full h-screen z-9 bg-gray-900/50"></div>
            <!-- End Small Device Overlay -->

            @include('backend.layouts.partials.header')

            <!-- Main Content -->
            <main
                x-data="{ show: false }"
                x-init="setTimeout(() => show = true, 50)"
                x-show="show"
                x-transition:enter="transition-all ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="min-h-[calc(100vh-80px)]">
                @yield('admin-content')
            </main>
            <!-- End Main Content -->
        </div>
    </div>

    {!! ld_apply_filters('admin_footer_before', '') !!}

    @stack('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const html = document.documentElement;
            const darkModeToggle = document.getElementById('darkModeToggle');
            const header = document.getElementById('appHeader');


            // Update header background based on current mode
            function updateHeaderBg() {
                if (!header) return;
                const isDark = html.classList.contains('dark');
            }

            // Initialize dark mode - default to true (dark mode)
            const savedDarkMode = localStorage.getItem('darkMode');
            if (savedDarkMode === null || savedDarkMode === 'true') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }

            updateHeaderBg();

            const observer = new MutationObserver(updateHeaderBg);
            observer.observe(html, {
                attributes: true,
                attributeFilter: ['class']
            });

            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isDark = html.classList.toggle('dark');
                    localStorage.setItem('darkMode', isDark);
                    updateHeaderBg();
                });
            }

            // Initialize sidebar state from localStorage if it exists
            if (window.Alpine) {
                const sidebarState = localStorage.getItem('sidebarToggle');
                if (sidebarState !== null) {
                    document.addEventListener('alpine:initialized', () => {
                        // Ensure the Alpine.js instance is ready
                        setTimeout(() => {
                            const alpineData = document.querySelector('body').__x;
                            if (alpineData && typeof alpineData.$data !== 'undefined') {
                                alpineData.$data.sidebarToggle = JSON.parse(sidebarState);
                            }
                        }, 0);
                    });
                }
            }
        });
    </script>
    
    @if (!empty(config('settings.global_custom_js')))
    <script>
        {!! config('settings.global_custom_js') !!}
    </script>
    @endif

    <!-- Global drawer handling script -->
    <script>
        // Define the global drawer opener function
        window.openDrawer = function(drawerId) {
            console.log('Opening drawer:', drawerId);
            
            // Method 1: Try using the LaraDrawers registry if available
            if (window.LaraDrawers && window.LaraDrawers[drawerId]) {
                console.log('Opening drawer via registry');
                window.LaraDrawers[drawerId].open = true;
                return;
            }
            
            // Method 2: Try using Alpine.js directly
            const drawerEl = document.querySelector(`[data-drawer-id="${drawerId}"]`);
            if (drawerEl && window.Alpine) {
                console.log('Opening drawer via Alpine');
                try {
                    const alpineInstance = Alpine.getComponent(drawerEl);
                    if (alpineInstance) {
                        alpineInstance.open = true;
                        return;
                    }
                } catch (e) {
                    console.error('Alpine error:', e);
                }
            }
            
            // Method 3: Dispatch a custom event as fallback
            console.log('Opening drawer via event dispatch');
            window.dispatchEvent(new CustomEvent('open-drawer-' + drawerId));
        };
        
        // Initialize all drawer triggers on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-drawer-trigger]').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    const drawerId = this.getAttribute('data-drawer-trigger');
                    if (drawerId) {
                        e.preventDefault();
                        window.openDrawer(drawerId);
                        return false;
                    }
                });
            });
        });
    </script>
    
    <x-toast-notifications />

    {{-- Flash Message to Toast Notifications --}}
    @if(session('success') || session('error') || session('warning') || session('info'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        variant: 'success',
                        title: 'Success',
                        message: @json(session('success'))
                    }
                }));
            @endif
            @if(session('error'))
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        variant: 'error',
                        title: 'Error',
                        message: @json(session('error'))
                    }
                }));
            @endif
            @if(session('warning'))
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        variant: 'warning',
                        title: 'Warning',
                        message: @json(session('warning'))
                    }
                }));
            @endif
            @if(session('info'))
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        variant: 'info',
                        title: 'Info',
                        message: @json(session('info'))
                    }
                }));
            @endif
        });
    </script>
    @endif

    {!! ld_apply_filters('admin_footer_after', '') !!}
</body>
</html>
