@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">

            {!! ld_apply_filters('users_after_breadcrumbs', '') !!}

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {{-- Toolbar --}}
                <div class="px-5 py-4 sm:px-6 flex flex-col md:flex-row justify-between items-center gap-3">
                    @include('backend.partials.search-form', [
                        'placeholder' => __('Search by name or email'),
                    ])
                    <div class="flex items-center gap-2">
                        {{-- Bulk Actions --}}
                        <div class="flex items-center justify-center" x-show="selectedUsers.length > 0">
                            <button id="bulkActionsButton" data-dropdown-toggle="bulkActionsDropdown"
                                class="btn-secondary flex items-center justify-center gap-1.5 text-sm" type="button">
                                <iconify-icon icon="lucide:more-vertical" width="15"></iconify-icon>
                                <span>{{ __('Bulk') }} (<span x-text="selectedUsers.length"></span>)</span>
                                <iconify-icon icon="lucide:chevron-down" width="14"></iconify-icon>
                            </button>
                            <div id="bulkActionsDropdown"
                                class="z-10 hidden w-48 p-1.5 bg-white rounded-lg shadow-lg border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                                <ul>
                                    <li class="cursor-pointer flex items-center gap-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-2 rounded-md transition-colors"
                                        @click="bulkDeleteModalOpen = true">
                                        <iconify-icon icon="lucide:trash-2" width="15"></iconify-icon>
                                        {{ __('Delete Selected') }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Role Filter --}}
                        <div class="flex items-center justify-center">
                            <button id="roleDropdownButton" data-dropdown-toggle="roleDropdown"
                                class="btn-secondary flex items-center justify-center gap-1.5 text-sm" type="button">
                                <iconify-icon icon="lucide:filter" width="15"></iconify-icon>
                                {{ request('role') ? ucfirst(request('role')) : __('All Roles') }}
                                <iconify-icon icon="lucide:chevron-down" width="14"></iconify-icon>
                            </button>
                            <div id="roleDropdown"
                                class="z-10 hidden w-52 p-1.5 bg-white rounded-lg shadow-lg border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                                <ul>
                                    <li class="cursor-pointer text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-2 rounded-md transition-colors {{ !request('role') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 font-medium' : '' }}"
                                        onclick="handleRoleFilter('')">
                                        {{ __('All Roles') }}
                                    </li>
                                    @foreach ($roles as $id => $name)
                                        @if ($name !== 'Superadmin')
                                        <li class="cursor-pointer text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-2 rounded-md transition-colors {{ request('role') === $name ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 font-medium' : '' }}"
                                            onclick="handleRoleFilter('{{ $name }}')">
                                            {{ ucfirst($name) }}
                                        </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        @if (auth()->user()->can('user.edit'))
                            <a href="{{ route('admin.users.create') }}" class="btn-primary flex items-center gap-1.5 text-sm">
                                <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                                {{ __('New User') }}
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Sort Bar --}}
                <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-2.5 sm:px-6 flex items-center gap-4">
                    <div class="flex items-center gap-1">
                        <input type="checkbox"
                            class="form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                            x-model="selectAll"
                            @click="selectAll = !selectAll; selectedUsers = selectAll ? [...document.querySelectorAll('.user-checkbox')].map(cb => cb.value) : [];">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('All') }}</span>
                    </div>
                    <div class="flex items-center gap-3 ml-auto">
                        <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Sort') }}:</span>
                        <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'name' ? '-name' : 'name']) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                            {{ __('Name') }}
                            @if (request()->sort === 'name')
                                <iconify-icon icon="lucide:arrow-up" width="13" class="text-indigo-500"></iconify-icon>
                            @elseif(request()->sort === '-name')
                                <iconify-icon icon="lucide:arrow-down" width="13" class="text-indigo-500"></iconify-icon>
                            @else
                                <iconify-icon icon="lucide:arrow-up-down" width="13" class="opacity-40"></iconify-icon>
                            @endif
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'email' ? '-email' : 'email']) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                            {{ __('Email') }}
                            @if (request()->sort === 'email')
                                <iconify-icon icon="lucide:arrow-up" width="13" class="text-indigo-500"></iconify-icon>
                            @elseif(request()->sort === '-email')
                                <iconify-icon icon="lucide:arrow-down" width="13" class="text-indigo-500"></iconify-icon>
                            @else
                                <iconify-icon icon="lucide:arrow-up-down" width="13" class="opacity-40"></iconify-icon>
                            @endif
                        </a>
                    </div>
                </div>

                {{-- Row Cards --}}
                <div class="border-t border-gray-100 dark:border-gray-800 p-5 sm:p-6">
                    @if($users->count())
                        <div class="space-y-3">
                            @foreach ($users as $user)
                                @php
                                    $roleStyles = [
                                        'Superadmin' => ['bg' => 'bg-red-50 dark:bg-red-500/10', 'text' => 'text-red-700 dark:text-red-400', 'ring' => 'ring-red-600/10 dark:ring-red-500/20', 'dot' => 'bg-red-500'],
                                        'Admin'      => ['bg' => 'bg-indigo-50 dark:bg-indigo-500/10', 'text' => 'text-indigo-700 dark:text-indigo-400', 'ring' => 'ring-indigo-600/10 dark:ring-indigo-500/20', 'dot' => 'bg-indigo-500'],
                                        'Organizer'  => ['bg' => 'bg-amber-50 dark:bg-amber-500/10', 'text' => 'text-amber-700 dark:text-amber-400', 'ring' => 'ring-amber-600/10 dark:ring-amber-500/20', 'dot' => 'bg-amber-500'],
                                        'Team Manager' => ['bg' => 'bg-purple-50 dark:bg-purple-500/10', 'text' => 'text-purple-700 dark:text-purple-400', 'ring' => 'ring-purple-600/10 dark:ring-purple-500/20', 'dot' => 'bg-purple-500'],
                                        'player'     => ['bg' => 'bg-emerald-50 dark:bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'ring' => 'ring-emerald-600/10 dark:ring-emerald-500/20', 'dot' => 'bg-emerald-500'],
                                    ];
                                    $defaultStyle = ['bg' => 'bg-gray-50 dark:bg-gray-500/10', 'text' => 'text-gray-600 dark:text-gray-300', 'ring' => 'ring-gray-500/10 dark:ring-gray-500/20', 'dot' => 'bg-gray-400'];
                                    $team = $user->actualTeams->first();
                                @endphp
                                <div class="group bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent transition-all duration-300 ease-in-out hover:shadow-xl hover:border-blue-500 hover:scale-[1.02]">
                                    <div class="flex items-center p-3 gap-4">

                                        {{-- 1. Checkbox (flex-shrink-0) --}}
                                        <div class="flex-shrink-0" @click.stop>
                                            <input type="checkbox"
                                                class="user-checkbox form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                                value="{{ $user->id }}" x-model="selectedUsers"
                                                {{ !auth()->user()->canBeModified($user, 'user.delete') ? 'disabled' : '' }}>
                                        </div>

                                        {{-- 2. Avatar + Name + @username (~25%) --}}
                                        <div class="flex items-center gap-3 flex-shrink-0 min-w-0" style="flex-basis: 25%;">
                                            <a href="{{ route('admin.users.show', $user->id) }}" class="flex-shrink-0">
                                                @if($user->player && $user->player->image_path)
                                                    <img src="{{ asset('storage/' . $user->player->image_path) }}"
                                                        alt="{{ $user->name }}"
                                                        class="h-11 w-11 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700">
                                                @else
                                                    <img src="{{ ld_apply_filters('user_list_page_avatar_item', $user->getGravatarUrl(80), $user) }}"
                                                        alt="{{ $user->name }}"
                                                        class="h-11 w-11 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700">
                                                @endif
                                            </a>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.users.show', $user->id) }}" class="block">
                                                    <h3 class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ $user->name }}</h3>
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ '@' . $user->username }}</p>
                                                </a>
                                            </div>
                                        </div>

                                        {{-- 3. Email (~25%, hidden on mobile) --}}
                                        <div class="hidden md:flex items-center gap-2 min-w-0 text-sm text-gray-500 dark:text-gray-400" style="flex-basis: 25%;">
                                            <iconify-icon icon="lucide:mail" width="14" class="flex-shrink-0 opacity-60"></iconify-icon>
                                            <span class="truncate">{{ $user->email }}</span>
                                        </div>

                                        {{-- 4. Team name(s) (~20%, hidden on mobile) --}}
                                        <div class="hidden md:flex items-center gap-2 min-w-0 text-sm text-gray-500 dark:text-gray-400" style="flex-basis: 20%;">
                                            @if($user->actualTeams->isNotEmpty())
                                                <iconify-icon icon="lucide:shield" width="14" class="flex-shrink-0 opacity-60"></iconify-icon>
                                                <span class="truncate">{{ $user->actualTeams->pluck('name')->join(', ') }}</span>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                            @endif
                                        </div>

                                        {{-- 5. Role badges (~20%) --}}
                                        <div class="hidden sm:flex flex-wrap gap-1.5 min-w-0" style="flex-basis: 20%;">
                                            @foreach ($user->roles as $role)
                                                @php
                                                    $s = $roleStyles[$role->name] ?? $defaultStyle;
                                                    if (strtolower($role->name) === 'player') {
                                                        $playerStatus = $user->player?->status ?? 'pending';
                                                        $statusLabel = ucfirst($playerStatus);
                                                        $statusDot = match($playerStatus) {
                                                            'approved' => 'bg-emerald-500',
                                                            'rejected' => 'bg-red-500',
                                                            default    => 'bg-amber-500',
                                                        };
                                                    } else {
                                                        $statusLabel = 'Active';
                                                        $statusDot = 'bg-emerald-500';
                                                    }
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg ring-1 ring-inset {{ $s['bg'] }} {{ $s['text'] }} {{ $s['ring'] }}">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
                                                    {{ $role->name === 'player' ? 'Player' : $role->name }}
                                                    <span class="text-[10px] opacity-70">{{ $statusLabel }}</span>
                                                </span>
                                            @endforeach
                                        </div>

                                        {{-- 6. Actions dropdown (right-aligned) --}}
                                        <div class="flex items-center justify-end ml-auto flex-shrink-0" @click.stop>
                                            @php ld_apply_filters('user_list_page_table_row_before_action', '', $user) @endphp
                                            <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                                <x-buttons.action-item :href="route('admin.users.show', $user->id)" icon="eye"
                                                    :label="__('View')" />

                                                @if (auth()->user()->canBeModified($user))
                                                    <x-buttons.action-item :href="route('admin.users.edit', $user->id)" icon="pencil"
                                                        :label="__('Edit')" />
                                                @endif

                                                @if (auth()->user()->canBeModified($user, 'user.delete'))
                                                    <div x-data="{ deleteModalOpen: false }">
                                                        <x-buttons.action-item type="modal-trigger"
                                                            modal-target="deleteModalOpen" icon="trash"
                                                            :label="__('Delete')" class="text-red-600 dark:text-red-400" />

                                                        <x-modals.confirm-delete id="delete-modal-{{ $user->id }}"
                                                            title="{{ __('Delete User') }}"
                                                            content="{{ __('Are you sure you want to delete this user?') }}"
                                                            formId="delete-form-{{ $user->id }}"
                                                            formAction="{{ route('admin.users.destroy', $user->id) }}"
                                                            modalTrigger="deleteModalOpen"
                                                            cancelButtonText="{{ __('No, cancel') }}"
                                                            confirmButtonText="{{ __('Yes, Confirm') }}" />
                                                    </div>
                                                @endif

                                                @if (auth()->user()->can('user.login_as') && $user->id != auth()->user()->id)
                                                    <x-buttons.action-item :href="route('admin.users.login-as', $user->id)" icon="box-arrow-in-right"
                                                        :label="__('Login as')" />
                                                @endif
                                            </x-buttons.action-buttons>
                                            @php ld_apply_filters('user_list_page_table_row_after_action', '', $user) @endphp
                                        </div>

                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center gap-2 py-12">
                            <iconify-icon icon="lucide:users" width="32" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No users found') }}</p>
                        </div>
                    @endif
                </div>

                {{-- Pagination --}}
                @if($users->hasPages())
                    <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 sm:px-6">
                        {{ $users->withQueryString()->links() }}
                    </div>
                @endif
            </div>

            {{-- Bulk Delete Modal --}}
            <div x-cloak x-show="bulkDeleteModalOpen" x-transition.opacity.duration.200ms
                x-trap.inert.noscroll="bulkDeleteModalOpen" x-on:keydown.esc.window="bulkDeleteModalOpen = false"
                x-on:click.self="bulkDeleteModalOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/20 p-4 backdrop-blur-md"
                role="dialog" aria-modal="true" aria-labelledby="bulk-delete-modal-title">
                <div x-show="bulkDeleteModalOpen"
                    x-transition:enter="transition ease-out duration-200 delay-100"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    class="flex max-w-md w-full flex-col gap-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-500/15">
                            <iconify-icon icon="lucide:alert-triangle" width="20" class="text-red-600 dark:text-red-400"></iconify-icon>
                        </div>
                        <div class="flex-1">
                            <h3 id="bulk-delete-modal-title" class="font-semibold text-gray-900 dark:text-white">
                                {{ __('Delete Selected Users') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('This action cannot be undone.') }}</p>
                        </div>
                        <button x-on:click="bulkDeleteModalOpen = false" aria-label="close modal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <iconify-icon icon="lucide:x" width="18"></iconify-icon>
                        </button>
                    </div>
                    <div class="px-5">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ __('Are you sure you want to delete the selected users?') }}
                        </p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-700 px-5 py-4">
                        <form id="bulk-delete-form" action="{{ route('admin.users.bulk-delete') }}" method="POST" class="flex items-center gap-2">
                            @method('DELETE')
                            @csrf
                            <template x-for="id in selectedUsers" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <button type="button" x-on:click="bulkDeleteModalOpen = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 transition-colors">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                                {{ __('Delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                function handleRoleFilter(value) {
                    let currentUrl = new URL(window.location.href);
                    const sortParam = currentUrl.searchParams.get('sort');
                    currentUrl.search = '';
                    if (value) {
                        currentUrl.searchParams.set('role', value);
                    }
                    if (sortParam) {
                        currentUrl.searchParams.set('sort', sortParam);
                    }
                    window.location.href = currentUrl.toString();
                }
            </script>
        @endpush
    @endsection
