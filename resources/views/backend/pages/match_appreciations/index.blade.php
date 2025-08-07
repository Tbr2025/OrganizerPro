@extends('backend.layouts.app')

@section('title', 'Match Appreciations | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6" x-data="{ selectedUsers: [], selectAll: false, bulkDeleteModalOpen: false }">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs">
            <x-slot name="title_after">
                @if (request('role'))
                    <span
                        class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-white">
                        {{ ucfirst(request('role')) }}
                    </span>
                @endif
            </x-slot>
        </x-breadcrumbs>


        {!! ld_apply_filters('users_after_breadcrumbs', '') !!}

        <div class="space-y-6">
            <div class="px-5">
                <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-col md:flex-row justify-between items-center gap-3">
                    @include('backend.partials.search-form', [
                        'placeholder' => __('Search by name or email'),
                    ])
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center" x-show="selectedUsers.length > 0">
                                <button id="bulkActionsButton" data-dropdown-toggle="bulkActionsDropdown"
                                    class="btn-secondary flex items-center justify-center gap-2 text-sm" type="button">
                                    <iconify-icon icon="lucide:more-vertical"></iconify-icon>
                                    <span>{{ __('Bulk Actions') }} (<span x-text="selectedUsers.length"></span>)</span>
                                    <iconify-icon icon="lucide:chevron-down"></iconify-icon>
                                </button>

                                <div id="bulkActionsDropdown"
                                    class="z-10 hidden w-48 p-2 bg-white rounded-md shadow dark:bg-gray-700">
                                    <ul class="space-y-2">
                                        <li class="cursor-pointer flex items-center gap-1 text-sm text-red-600 dark:text-red-500 hover:bg-red-50 dark:hover:bg-red-500 dark:hover:text-red-50 px-2 py-1.5 rounded transition-colors duration-300"
                                            @click="bulkDeleteModalOpen = true">
                                            <iconify-icon icon="lucide:trash"></iconify-icon> {{ __('Delete Selected') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-center">
                            <button id="roleDropdownButton" data-dropdown-toggle="roleDropdown"
                                class="btn-secondary flex items-center justify-center gap-2" type="button">
                                <iconify-icon icon="lucide:sliders"></iconify-icon>
                                {{ __('Filter by Role') }}
                                <iconify-icon icon="lucide:chevron-down"></iconify-icon>
                            </button>
                        </div>

                        @if (auth()->user()->can('user.edit'))
                            <a href="{{ route('admin.appreciations.create') }}" class="btn-primary flex items-center gap-2">
                                <iconify-icon icon="feather:plus" height="16"></iconify-icon>
                                {{ __('New ') }}
                            </a>
                        @endif
                    </div>
                </div>


                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-700 uppercase">
                            <tr>
                                <th class="p-3"><input type="checkbox" class="form-checkbox"></th>
                                <th class="p-3 text-left">Match</th>
                                <th class="p-3 text-left">Player</th>
                                <th class="p-3 text-left">Title</th>
                                <th class="p-3 text-left">Image</th>
                                <th class="p-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                            @forelse ($appreciations as $appreciation)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3">
                                        <input type="checkbox" class="form-checkbox" value="{{ $appreciation->id }}">
                                    </td>
                                    <td class="p-3 whitespace-nowrap">{{ $appreciation->match->title ?? '—' }}</td>
                                    <td class="p-3 whitespace-nowrap">{{ $appreciation->player->name ?? '—' }}</td>
                                    <td class="p-3 whitespace-nowrap">{{ $appreciation->title }}</td>
                                    <td class="p-3">
                                        @if ($appreciation->image_path)
                                            <img src="{{ asset('storage/' . $appreciation->image_path) }}"
                                                class="h-10 w-10 rounded object-cover border" alt="Award Image">
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <form method="POST"
                                            action="{{ route('admin.appreciations.destroy', $appreciation) }}"
                                            onsubmit="return confirm('Are you sure you want to delete this appreciation?')"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-5 text-center text-gray-500">
                                        No appreciations found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="p-4 border-t bg-gray-50">
                        {{ $appreciations->links() }}
                    </div>
                </div>
            </div>
        </div>
    @endsection
