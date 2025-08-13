@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

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


            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <form method="GET" action="{{ route('admin.players.index') }}" class="flex gap-1">
                    <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-col md:flex-row justify-between items-center gap-1">
                        @include('backend.partials.search-form', [
                            'placeholder' => __('Search by name or email'),
                        ])
                        <select name="team_name" class="form-control !h-11 ">
                            <option value="">All Teams</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->name }}" @selected(request('team_name') == $team->name)>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="role" class="form-control !h-11 ">
                            <option value="">All Types</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->type }}" @selected(request('role') == $role->type)>
                                    {{ $role->type }}
                                </option>
                            @endforeach
                        </select>


                       @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('SuperAdmin') )

        <select name="status" id="status" class="form-control !h-11">
            <option value="">All Status</option>
            <option value="verified" @selected(request('status') == 'verified')>Verified</option>
            <option value="pending" @selected(request('status') == 'pending')>Pending</option>
        </select>
    
@endif
                        <select name="updated_sort" class="form-control !h-11">
                            <option value="">Sort by Last Updated</option>
                            <option value="desc" @selected(request('updated_sort') == 'desc')>Newest First</option>
                            <option value="asc" @selected(request('updated_sort') == 'asc')>Oldest First</option>
                        </select>

                        <button type="submit" class="btn-primary">Apply</button>
                        <a href="{{ route('admin.players.index') }}" class="btn-secondary">Reset</a>

                    </div>
                </form>

                <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 overflow-x-auto overflow-y-visible">
                    <table id="dataTable" class="w-full dark:text-gray-300">
                        <thead class="bg-light text-capitalize">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5 sm:px-6 w-4">
                                    <input type="checkbox"
                                        class="form-checkbox h-4 w-4 text-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                        x-model="selectAll"
                                        @click="
                            selectAll = !selectAll;
                            selectedPlayers = selectAll ?
                                [...document.querySelectorAll('.player-checkbox')].map(cb => cb.value) :
                                [];
                        ">
                                </th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">ID</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">Name</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">Phone</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">Team</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">Status</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5"> Update</th>
                                <th class="p-2 bg-gray-50 dark:bg-gray-800 text-left px-5">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($players as $player)
                                <tr class="border-b border-gray-100 dark:border-gray-800">

                                    <td class="px-5 py-4 sm:px-6">
                                        <input type="checkbox"
                                            class="player-checkbox form-checkbox h-4 w-4 text-primary border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                                            value="{{ $player->id }}" x-model="selectedPlayers">
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        @php
                                            $orgName = $player->user?->organization?->name;
                                            $prefix = '';
                                            if ($orgName) {
                                                $words = explode(' ', $orgName);
                                                $prefix = strtoupper(
                                                    substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1),
                                                );
                                            }
                                        @endphp
                                        {{ $prefix ? $prefix . '-' . $player->id : $player->id }}

                                    </td>




                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex items-center space-x-3">
                                            <div class="relative inline-block">
                                                <!-- Player profile image -->
                                                <img src="{{ $player->image_path ? Storage::url($player->image_path) : asset('images/icons/default-avatar.png') }}"
                                                    alt="{{ $player->name }}"
                                                    class="w-12 h-12 rounded-full border border-gray-300 object-cover">

                                                @if ($player->welcome_email_sent_at)
                                                    <!-- Verified badge -->
                                                    <span
                                                        class="absolute bottom-0 right-0 bg-blue-500 rounded-full p-1 border-2 border-white">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="white" class="w-3 h-3">
                                                            <path fill-rule="evenodd"
                                                                d="M22.5 12a10.5 10.5 0 11-21 0 10.5 10.5 0 0121 0zm-11.707 3.293l5.5-5.5a1 1 0 00-1.414-1.414L10 12.586 8.121 10.707a1 1 0 00-1.414 1.414l2.586 2.586a1 1 0 001.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Player name -->
                                            <span class="text-gray-900 font-medium">{{ $player->name }}</span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="space-y-1 text-sm text-gray-800">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-blue-600">Mobile:</span>
                                                <span class="whitespace-nowrap">+{{ $player->mobile_number_full }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-green-600">CricHeroes:</span>
                                                <span
                                                    class="whitespace-nowrap">+{{ $player->cricheroes_number_full }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 sm:px-6">

                                        @if ($player->team->name === 'Others')
                                            {{ $player->team_name_ref ?? '' }}
                                        @else
                                            {{ $player->team->name ?? 'N/A' }}
                                        @endif


                                    </td>

                                    <td class="px-5 py-4 sm:px-6">
                                        @php
                                            $isVerified = !is_null($player->welcome_email_sent_at);
                                        @endphp

                                        @if ($isVerified)
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                                                Verified
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                                                Pending
                                            </span>
                                        @endif

                                    </td>


                                    <td class="px-5 py-4 sm:px-6">

                                        @if ($player->updated_at)
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $player->updated_at->diffForHumans() }}
                                            </div>
                                        @endif

                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex flex-wrap items-center gap-2">

                                            {{-- Approve Button --}}
                                            {{-- <form action="{{ route('admin.players.approve', $player->id) }}"
                                                method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1 bg-green-100 text-green-700 hover:bg-green-200 text-sm font-medium px-3 py-1 rounded-md transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Approve
                                                </button>
                                            </form> --}}

                                            {{-- Reject Button --}}
                                            {{-- <form action="{{ route('admin.players.reject', $player->id) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1 bg-red-100 text-red-700 hover:bg-red-200 text-sm font-medium px-3 py-1 rounded-md transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </button>
                                            </form> --}}
                                            @if ($player->welcome_image_path && Storage::disk('public')->exists($player->welcome_image_path))
                                                <a href="{{ asset('storage/' . $player->welcome_image_path) }}" download
                                                    class="inline-flex items-center px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700">
                                                    Welcome Card
                                                </a>
                                            @else
                                                <p class="text-gray-500 italic"></p>
                                            @endif
                                        <!-- This assumes you have Alpine.js included in your project -->
<div x-data="{ open: false }" class="relative">
    
    <!-- Kebab Menu Icon Button -->
    <button @click="open = !open" @click.away="open = false"
            class="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01" />
        </svg>
    </button>

    <!-- Dropdown Panel -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute z-10 w-48 right-0 mt-2 origin-top-right bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
         style="display: none;">
        <div class="py-1" role="menu" aria-orientation="vertical">
            
            @canany(['player.show', 'player.view'])
                <a href="{{ route('admin.players.show', $player->id) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    View
                </a>
            @endcanany

            @can('player.edit')
                <a href="{{ route('admin.players.edit', $player->id) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" /></svg>
                    Edit
                </a>
            @endcan

            @can('player.delete')
                 <form action="{{ route('admin.players.destroy', $player->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this player? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-900/50" role="menuitem">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>
</div>

                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-500 dark:text-gray-300">
                                        No players found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="my-4 px-4 sm:px-6">
                        {{ $players->links() }}
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
