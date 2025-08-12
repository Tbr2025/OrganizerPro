@extends('backend.layouts.app')

@section('title', 'Teams | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6 lg:p-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">
                Teams
            </h1>
            {{-- "Create New" Button --}}
            @can('actual-team.create')
                <a href="{{ route('admin.actual-teams.create') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create New Team
                </a>
            @endcan
        </div>

        {{-- Teams Grid --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($actualTeams as $team)
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700
                            transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                    
                    {{-- Card Header: Logo and Name --}}
                    <div class="flex items-center gap-4 p-4 border-b border-gray-200 dark:border-gray-700">
                        {{-- Team Logo Placeholder --}}
                        {{-- TODO: Replace with actual team logo if available, e.g., <img src="{{ $team->logo_url }}"> --}}
                        <div class="flex-shrink-0 w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                             <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white truncate" title="{{ $team->name }}">
                                {{ $team->name }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $team->organization->name ?? 'No Organization' }}</p>
                        </div>
                    </div>

                    {{-- Card Body: Details --}}
                    <div class="p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-500 dark:text-gray-400">Tournament:</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $team->tournament->name ?? '-' }}</span>
                        </div>
                        {{-- You can add more details here if needed --}}
                        {{-- <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-500 dark:text-gray-400">Players:</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $team->players_count }}</span>
                        </div> --}}
                    </div>

                    {{-- Card Footer: Actions --}}
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-end space-x-2">
                            {{-- View Button --}}
                            @can('actual-team.view')
                                <a href="{{ route('admin.actual-teams.show', $team) }}" title="View Details" class="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                            @endcan

                            {{-- Edit Button --}}
                            @can('actual-team.edit')
                                <a href="{{ route('admin.actual-teams.edit', $team) }}" title="Edit Team" class="p-2 text-blue-500 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" /></svg>
                                </a>
                            @endcan

                            {{-- Delete Button --}}
                            @can('actual-team.delete')
                                <form action="{{ route('admin.actual-teams.destroy', $team) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this team?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete Team" class="p-2 text-red-500 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                {{-- Improved "No Teams" State --}}
                <div class="col-span-1 sm:col-span-2 lg:col-span-3 xl:col-span-4 p-8 text-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No teams found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new team.</p>
                    @can('actual-team.create')
                        <div class="mt-6">
                            <a href="{{ route('admin.actual-teams.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create New Team
                            </a>
                        </div>
                    @endcan
                </div>
            @endforelse
        </div>

        {{-- Pagination Links --}}
        <div class="mt-8">
            {{ $actualTeams->links() }}
        </div>
    </div>
@endsection