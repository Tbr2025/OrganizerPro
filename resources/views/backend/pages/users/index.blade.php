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
                                        @if ($name !== 'Player' && $name !== 'Superadmin')
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

                {{-- Table --}}
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="w-full">
                        <thead>
                            <tr class="border-t border-b border-gray-100 dark:border-gray-800">
                                <th class="w-12 py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <input type="checkbox"
                                        class="form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                        x-model="selectAll"
                                        @click="selectAll = !selectAll; selectedUsers = selectAll ? [...document.querySelectorAll('.user-checkbox')].map(cb => cb.value) : [];">
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'name' ? '-name' : 'name']) }}"
                                       class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                        {{ __('Name') }}
                                        @if (request()->sort === 'name')
                                            <iconify-icon icon="lucide:arrow-up" width="14" class="text-indigo-500"></iconify-icon>
                                        @elseif(request()->sort === '-name')
                                            <iconify-icon icon="lucide:arrow-down" width="14" class="text-indigo-500"></iconify-icon>
                                        @else
                                            <iconify-icon icon="lucide:arrow-up-down" width="14" class="opacity-40"></iconify-icon>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'email' ? '-email' : 'email']) }}"
                                       class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                        {{ __('Email') }}
                                        @if (request()->sort === 'email')
                                            <iconify-icon icon="lucide:arrow-up" width="14" class="text-indigo-500"></iconify-icon>
                                        @elseif(request()->sort === '-email')
                                            <iconify-icon icon="lucide:arrow-down" width="14" class="text-indigo-500"></iconify-icon>
                                        @else
                                            <iconify-icon icon="lucide:arrow-up-down" width="14" class="opacity-40"></iconify-icon>
                                        @endif
                                    </a>
                                </th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Roles') }}</span>
                                </th>
                                @php ld_apply_filters('user_list_page_table_header_before_action', '') @endphp
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-right">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Action') }}</span>
                                </th>
                                @php ld_apply_filters('user_list_page_table_header_after_action', '') @endphp
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($users as $user)
                                <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                                    <td class="px-5 py-3.5">
                                        <input type="checkbox"
                                            class="user-checkbox form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                            value="{{ $user->id }}" x-model="selectedUsers"
                                            {{ !auth()->user()->canBeModified($user, 'user.delete') ? 'disabled' : '' }}>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <a data-tooltip-target="tooltip-user-{{ $user->id }}"
                                            href="{{ auth()->user()->canBeModified($user) ? route('admin.users.edit', $user->id) : '#' }}"
                                            class="flex items-center gap-3 min-w-[180px]">
                                            <img src="{{ ld_apply_filters('user_list_page_avatar_item', $user->getGravatarUrl(40), $user) }}"
                                                alt="{{ $user->name }}"
                                                class="w-9 h-9 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700 flex-shrink-0">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ '@' . $user->username }}</p>
                                            </div>
                                        </a>
                                        @if (auth()->user()->canBeModified($user))
                                            <div id="tooltip-user-{{ $user->id }}"
                                                class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 rounded-lg shadow-xs opacity-0 tooltip dark:bg-gray-700">
                                                {{ __('Edit User') }}
                                                <div class="tooltip-arrow" data-popper-arrow></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $user->email }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->roles as $role)
                                                @php
                                                    $roleColors = [
                                                        'Superadmin' => 'bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20',
                                                        'Admin' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-500/20',
                                                        'Organizer' => 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
                                                        'Team Manager' => 'bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-500/20',
                                                    ];
                                                    $colors = $roleColors[$role->name] ?? 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-500/20';
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md ring-1 ring-inset {{ $colors }}">
                                                    @if (auth()->user()->can('role.edit'))
                                                        <a href="{{ route('admin.roles.edit', $role->id) }}"
                                                            data-tooltip-target="tooltip-role-{{ $role->id }}-{{ $user->id }}"
                                                            class="hover:underline">
                                                            {{ $role->name }}
                                                        </a>
                                                        <div id="tooltip-role-{{ $role->id }}-{{ $user->id }}"
                                                            role="tooltip"
                                                            class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 rounded-lg shadow-xs opacity-0 tooltip dark:bg-gray-700">
                                                            {{ __('Edit') }} {{ $role->name }} {{ __('Role') }}
                                                            <div class="tooltip-arrow" data-popper-arrow></div>
                                                        </div>
                                                    @else
                                                        {{ $role->name }}
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    @php ld_apply_filters('user_list_page_table_row_before_action', '', $user) @endphp
                                    <td class="px-5 py-3.5 text-right">
                                        <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
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
                                    </td>
                                    @php ld_apply_filters('user_list_page_table_row_after_action', '', $user) @endphp
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <iconify-icon icon="lucide:users" width="32" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No users found') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($users->hasPages())
                    <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 sm:px-6">
                        {{ $users->links() }}
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
