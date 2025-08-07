@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('before_vite_build')
    <script>
        var userGrowthData = @json($user_growth_data['data']);
        var userGrowthLabels = @json($user_growth_data['labels']);
    </script>
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        {!! ld_apply_filters('dashboard_after_breadcrumbs', '') !!}
        @role('Player')
            <div class="bg-white rounded-2xl shadow-lg p-10 max-w-md w-full text-center border border-blue-100">
                <div class="mb-6">
                    <h1 class="text-3xl font-extrabold text-blue-700 mb-1">👋 Welcome to</h1>
                    <h2 class="text-2xl font-semibold text-gray-800">{{ config('app.name') }}</h2>
                </div>

                <p class="text-lg text-gray-700 mb-8">
                    Hello, <span class="font-semibold">{{ Auth::user()->name ?? 'Guest' }}</span>!
                </p>

                @auth
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('profileplayers.edit') }}"
                            class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm transition duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Update Profile
                        </a>

                        <a href="{{ route('profile.edit') }}"
                            class="inline-flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm transition duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m0-6v2m-6 4h12M12 6a9 9 0 100 18 9 9 0 000-18z" />
                            </svg>
                            Change Password
                        </a>
                    </div>
                @endauth
            </div>


            {!! ld_apply_filters('dashboard_cards_after_player', '') !!}
        @endrole
        @role('Admin')
            <div class="grid grid-cols-12 gap-4 md:gap-6">
                <div class="col-span-12 space-y-6">
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-4 md:gap-6">
                        {!! ld_apply_filters('dashboard_cards_before_users', '') !!}
                        @include('backend.pages.dashboard.partials.card', [
                            'icon' => 'heroicons:user-group',
                            'icon_bg' => '#635BFF',
                            'label' => __('Users'),
                            'value' => $total_users,
                            'class' => 'bg-white',
                            'url' => route('admin.users.index'),
                            'enable_full_div_click' => true,
                        ])
                        {!! ld_apply_filters('dashboard_cards_after_users', '') !!}
                        @include('backend.pages.dashboard.partials.card', [
                            'icon' => 'heroicons:key',
                            'icon_bg' => '#00D7FF',
                            'label' => __('Roles'),
                            'value' => $total_roles,
                            'class' => 'bg-white',
                            'url' => route('admin.roles.index'),
                            'enable_full_div_click' => true,
                        ])
                        {!! ld_apply_filters('dashboard_cards_after_roles', '') !!}
                        @include('backend.pages.dashboard.partials.card', [
                            'icon' => 'bi:shield-check',
                            'icon_bg' => '#FF4D96',
                            'label' => __('Permissions'),
                            'value' => $total_permissions,
                            'class' => 'bg-white',
                            'url' => route('admin.permissions.index'),
                            'enable_full_div_click' => true,
                        ])
                        {!! ld_apply_filters('dashboard_cards_after_permissions', '') !!}
                        @include('backend.pages.dashboard.partials.card', [
                            'icon' => 'heroicons:language',
                            'icon_bg' => '#22C55E',
                            'label' => __('Translations'),
                            'value' => $languages['total'] . ' / ' . $languages['active'],
                            'class' => 'bg-white',
                            'url' => route('admin.translations.index'),
                            'enable_full_div_click' => true,
                        ])
                        {!! ld_apply_filters('dashboard_cards_after_translations', '') !!}
                    </div>
                </div>
            </div>

            {!! ld_apply_filters('dashboard_cards_after', '') !!}

            <div class="mt-6">
                <div class="grid grid-cols-12 gap-4 md:gap-6">
                    <div class="col-span-12">
                        <div class="grid grid-cols-12 gap-4 md:gap-6">
                            <div class="col-span-12 md:col-span-8">
                                @include('backend.pages.dashboard.partials.user-growth')
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                @include('backend.pages.dashboard.partials.user-history')
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <div class="grid grid-cols-12 gap-4 md:gap-6">
                    <div class="col-span-12">
                        <div class="grid grid-cols-12 gap-4 md:gap-6">
                            @include('backend.pages.dashboard.partials.post-chart')
                        </div>
                    </div>
                </div>
            </div>

            {!! ld_apply_filters('dashboard_after', '') !!}
        </div>
    @endrole
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endpush
