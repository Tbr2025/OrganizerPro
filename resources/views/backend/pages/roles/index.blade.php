@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6" x-data="{ selectedRoles: [], selectAll: false, bulkDeleteModalOpen: false }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {!! ld_apply_filters('roles_after_breadcrumbs', '') !!}

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            {{-- Toolbar --}}
            <div class="px-5 py-4 sm:px-6 flex flex-col md:flex-row justify-between items-center gap-3">
                @include('backend.partials.search-form', [
                    'placeholder' => __('Search by role name'),
                ])
                <div class="flex items-center gap-2">
                    {{-- Bulk Actions --}}
                    <div class="flex items-center justify-center" x-show="selectedRoles.length > 0">
                        <button id="bulkActionsButton" data-dropdown-toggle="bulkActionsDropdown"
                            class="btn-secondary flex items-center justify-center gap-1.5 text-sm" type="button">
                            <iconify-icon icon="lucide:more-vertical" width="15"></iconify-icon>
                            <span>{{ __('Bulk') }} (<span x-text="selectedRoles.length"></span>)</span>
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

                    @if (auth()->user()->can('role.create'))
                        <a href="{{ route('admin.roles.create') }}" class="btn-primary flex items-center gap-1.5 text-sm">
                            <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                            {{ __('New Role') }}
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
                                    @click="selectAll = !selectAll; selectedRoles = selectAll ? [...document.querySelectorAll('.role-checkbox')].map(cb => cb.value) : [];">
                            </th>
                            <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'name' ? '-name' : 'name']) }}"
                                   class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                    {{ __('Role') }}
                                    @if(request()->sort === 'name')
                                        <iconify-icon icon="lucide:arrow-up" width="14" class="text-indigo-500"></iconify-icon>
                                    @elseif(request()->sort === '-name')
                                        <iconify-icon icon="lucide:arrow-down" width="14" class="text-indigo-500"></iconify-icon>
                                    @else
                                        <iconify-icon icon="lucide:arrow-up-down" width="14" class="opacity-40"></iconify-icon>
                                    @endif
                                </a>
                            </th>
                            <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => request()->sort === 'user_count' ? '-user_count' : 'user_count']) }}"
                                   class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                                    {{ __('Users') }}
                                    @if(request()->sort === 'user_count')
                                        <iconify-icon icon="lucide:arrow-up" width="14" class="text-indigo-500"></iconify-icon>
                                    @elseif(request()->sort === '-user_count')
                                        <iconify-icon icon="lucide:arrow-down" width="14" class="text-indigo-500"></iconify-icon>
                                    @else
                                        <iconify-icon icon="lucide:arrow-up-down" width="14" class="opacity-40"></iconify-icon>
                                    @endif
                                </a>
                            </th>
                            <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-left">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Permissions') }}</span>
                            </th>
                            <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-right">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Action') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($roles as $role)
                            @php
                                $roleIcons = [
                                    'Superadmin' => ['icon' => 'lucide:shield-check', 'color' => 'text-red-500 dark:text-red-400', 'bg' => 'bg-red-50 dark:bg-red-500/10'],
                                    'Admin' => ['icon' => 'lucide:shield', 'color' => 'text-indigo-500 dark:text-indigo-400', 'bg' => 'bg-indigo-50 dark:bg-indigo-500/10'],
                                    'Organizer' => ['icon' => 'lucide:crown', 'color' => 'text-amber-500 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
                                    'Team Manager' => ['icon' => 'lucide:users', 'color' => 'text-purple-500 dark:text-purple-400', 'bg' => 'bg-purple-50 dark:bg-purple-500/10'],
                                    'Team Owner' => ['icon' => 'lucide:briefcase', 'color' => 'text-teal-500 dark:text-teal-400', 'bg' => 'bg-teal-50 dark:bg-teal-500/10'],
                                    'Coach' => ['icon' => 'lucide:clipboard-list', 'color' => 'text-cyan-500 dark:text-cyan-400', 'bg' => 'bg-cyan-50 dark:bg-cyan-500/10'],
                                    'Captain' => ['icon' => 'lucide:flag', 'color' => 'text-orange-500 dark:text-orange-400', 'bg' => 'bg-orange-50 dark:bg-orange-500/10'],
                                    'Player' => ['icon' => 'lucide:user', 'color' => 'text-emerald-500 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
                                    'Scorer' => ['icon' => 'lucide:hash', 'color' => 'text-sky-500 dark:text-sky-400', 'bg' => 'bg-sky-50 dark:bg-sky-500/10'],
                                    'Viewer' => ['icon' => 'lucide:eye', 'color' => 'text-gray-500 dark:text-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-500/10'],
                                    'Editor' => ['icon' => 'lucide:pen-line', 'color' => 'text-pink-500 dark:text-pink-400', 'bg' => 'bg-pink-50 dark:bg-pink-500/10'],
                                    'Subscriber' => ['icon' => 'lucide:bell', 'color' => 'text-violet-500 dark:text-violet-400', 'bg' => 'bg-violet-50 dark:bg-violet-500/10'],
                                    'Contact' => ['icon' => 'lucide:mail', 'color' => 'text-blue-500 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
                                ];
                                $ri = $roleIcons[$role->name] ?? ['icon' => 'lucide:key', 'color' => 'text-gray-500 dark:text-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-500/10'];
                                $permCount = $role->permissions->count();
                            @endphp
                            <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                                <td class="px-5 py-3.5">
                                    <input type="checkbox"
                                        class="role-checkbox form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                        value="{{ $role->id }}" x-model="selectedRoles"
                                        {{ $role->name === 'superadmin' ? 'disabled' : '' }}>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="flex items-center justify-center w-9 h-9 rounded-lg {{ $ri['bg'] }} flex-shrink-0">
                                            <iconify-icon icon="{{ $ri['icon'] }}" width="18" class="{{ $ri['color'] }}"></iconify-icon>
                                        </span>
                                        <div class="min-w-0">
                                            @if (auth()->user()->can('role.edit'))
                                                <a href="{{ route('admin.roles.edit', $role->id) }}"
                                                   class="text-sm font-semibold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {{ $role->name }}
                                                </a>
                                            @else
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $role->name }}</span>
                                            @endif
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $permCount }} {{ __('permissions') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.users.index', ['role' => $role->name]) }}"
                                       class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                       title="{{ __('View') }} {{ $role->name }} {{ __('Users') }}">
                                        <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 text-xs font-semibold rounded-md
                                            {{ $role->user_count > 0 ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-500' }}">
                                            {{ $role->user_count }}
                                        </span>
                                    </a>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div x-data="{ showAll: false }">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($role->permissions->take(5) as $permission)
                                                @php
                                                    $parts = explode('.', $permission->name);
                                                    $permGroup = $parts[0] ?? '';
                                                    $permAction = $parts[1] ?? '';
                                                    $groupColors = [
                                                        'dashboard' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
                                                        'tournament' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
                                                        'match' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400',
                                                        'team' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400',
                                                        'actual-team' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400',
                                                        'player' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                                                        'user' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
                                                        'role' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                                                        'settings' => 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-400',
                                                        'auction' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
                                                    ];
                                                    $pc = $groupColors[$permGroup] ?? 'bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
                                                @endphp
                                                <span class="inline-flex items-center px-1.5 py-0.5 text-[11px] font-medium rounded {{ $pc }}">
                                                    {{ $permission->name }}
                                                </span>
                                            @endforeach
                                            <template x-if="showAll">
                                                <span class="contents">
                                                    @foreach ($role->permissions->skip(5) as $permission)
                                                        @php
                                                            $parts = explode('.', $permission->name);
                                                            $permGroup = $parts[0] ?? '';
                                                            $pc = $groupColors[$permGroup] ?? 'bg-gray-50 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
                                                        @endphp
                                                        <span class="inline-flex items-center px-1.5 py-0.5 text-[11px] font-medium rounded {{ $pc }}">
                                                            {{ $permission->name }}
                                                        </span>
                                                    @endforeach
                                                </span>
                                            </template>
                                        </div>
                                        @if ($permCount > 5)
                                            <button @click="showAll = !showAll" class="mt-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                <span x-show="!showAll">+{{ $permCount - 5 }} {{ __('more') }}</span>
                                                <span x-show="showAll">{{ __('Show less') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                        @if (auth()->user()->can('role.edit') && $role->name != 'superadmin')
                                            <x-buttons.action-item :href="route('admin.roles.edit', $role->id)" icon="pencil" :label="__('Edit')" />
                                        @endif

                                        @if (auth()->user()->can('role.delete') && $role->name != 'superadmin')
                                            <div x-data="{ deleteModalOpen: false }">
                                                <x-buttons.action-item type="modal-trigger" modal-target="deleteModalOpen" icon="trash"
                                                    :label="__('Delete')" class="text-red-600 dark:text-red-400" />
                                                <x-modals.confirm-delete id="delete-modal-{{ $role->id }}"
                                                    title="{{ __('Delete Role') }}"
                                                    content="{{ __('Are you sure you want to delete this role?') }}"
                                                    formId="delete-form-{{ $role->id }}"
                                                    formAction="{{ route('admin.roles.destroy', $role->id) }}"
                                                    modalTrigger="deleteModalOpen"
                                                    cancelButtonText="{{ __('No, cancel') }}"
                                                    confirmButtonText="{{ __('Yes, Confirm') }}" />
                                            </div>
                                        @endif

                                        <x-buttons.action-item :href="route('admin.users.index', ['role' => $role->name])" icon="people" :label="__('View Users')" />
                                    </x-buttons.action-buttons>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-12">
                                    <div class="flex flex-col items-center gap-2">
                                        <iconify-icon icon="lucide:shield-off" width="32" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                                        <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No roles found') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($roles->hasPages())
                <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4 sm:px-6">
                    {{ $roles->links() }}
                </div>
            @endif
        </div>
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
                        {{ __('Delete Selected Roles') }}
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
                    {{ __('Are you sure you want to delete the selected roles?') }}
                </p>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-700 px-5 py-4">
                <form id="bulk-delete-form" action="{{ route('admin.roles.bulk-delete') }}" method="POST" class="flex items-center gap-2">
                    @method('DELETE')
                    @csrf
                    <template x-for="id in selectedRoles" :key="id">
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
@endsection
