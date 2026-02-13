@extends('backend.layouts.app')

@section('title', 'Tournaments | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Tournaments</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Browse and manage all official tournaments.</p>
        </div>
        @can('tournament.create') {{-- Assuming a permission for creation --}}
            <a href="{{ route('admin.tournaments.create') }}"
                class="btn btn-primary inline-flex items-center gap-2">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" /></svg>
                Add New Tournament
            </a>
        @endcan
    </div>

    {{-- Search Bar --}}
    <div class="mb-6">
        <form method="GET">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by tournament name or location..."
                       class="form-control pl-10">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
        </form>
    </div>

    {{-- Tournaments Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse ($tournaments as $tournament)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700
                        flex flex-col transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                
                {{-- Card Header --}}
                <div class="p-5 bg-gradient-to-r from-blue-600 to-indigo-700 dark:from-blue-800 dark:to-indigo-900 rounded-t-lg">
                    <h2 class="text-xl font-bold text-white truncate" title="{{ $tournament->name }}">
                        {{ $tournament->name }}
                    </h2>
                     @if(auth()->user()->hasRole('Superadmin'))
                        <p class="text-sm text-blue-200 mt-1">{{ $tournament->organization->name ?? 'No Organization' }}</p>
                    @endif
                </div>

                {{-- Card Body --}}
                <div class="p-5 space-y-4 flex-grow">
                    <div class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>{{ \Carbon\Carbon::parse($tournament->start_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($tournament->end_date)->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                         <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span>{{ $tournament->location ?? 'Location TBD' }}</span>
                    </div>
                </div>
                
                {{-- Quick Actions --}}
                <div class="px-5 pb-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                       class="text-xs px-2 py-1 bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 rounded hover:bg-purple-200 dark:hover:bg-purple-800">
                        Templates
                    </a>
                    <a href="{{ route('admin.tournaments.calendar.index', $tournament) }}"
                       class="text-xs px-2 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-800">
                        Calendar
                    </a>
                    <a href="{{ route('admin.tournaments.settings.edit', $tournament) }}"
                       class="text-xs px-2 py-1 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                        Settings
                    </a>
                    <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                       class="text-xs px-2 py-1 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded hover:bg-green-200 dark:hover:bg-green-800">
                        Registrations
                    </a>
                </div>

                {{-- Card Footer with Actions --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end space-x-3">
                    {{-- Admins and Superadmins see Edit and Delete buttons --}}
                    @if(auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                        <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="btn btn-secondary btn-sm">
                            Edit
                        </a>
                        <form action="{{ route('admin.tournaments.destroy', $tournament) }}" method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this tournament?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="md:col-span-2 lg:col-span-3 p-8 text-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tournaments found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new tournament.</p>
                @can('tournament.create')
                    <div class="mt-6">
                        <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary">
                            Add New Tournament
                        </a>
                    </div>
                @endcan
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $tournaments->withQueryString()->links() }}
    </div>
</div>
@endsection