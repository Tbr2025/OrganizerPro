@extends('backend.layouts.app')

@section('title', 'Teams | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
        {{-- Breadcrumbs (assuming you have this component) --}}
        {{-- <x-breadcrumbs :breadcrumbs="$breadcrumbs" /> --}}

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ __('Teams') }}</h1>
                @can('actual-team.create')
                    <a href="{{ route('admin.actual-teams.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                        </svg>
                        {{ __('Add New Team') }}
                    </a>
                @endcan
            </div>

            <!-- ======================================================= -->
            <!-- START: Filter Form Section -->
            <!-- ======================================================= -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border border-gray-200 dark:border-gray-700">
                <form action="{{ route('admin.actual-teams.index') }}" method="GET">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">

                        {{-- Organization Filter (Only for Superadmins) --}}
                        @if (auth()->user()->hasRole('Superadmin'))
                            <div>
                                <label for="organization_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by
                                    Organization</label>
                                <select name="organization_id" id="organization_id" class="form-control mt-1">
                                    <option value="">All Organizations</option>
                                    @foreach ($organizations as $organization)
                                        <option value="{{ $organization->id }}"
                                            {{ request('organization_id') == $organization->id ? 'selected' : '' }}>
                                            {{ $organization->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Tournament Filter --}}
                        <div>
                            <label for="tournament_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by
                                Tournament</label>
                            <select name="tournament_id" id="tournament_id" class="form-control mt-1">
                                <option value="">All Tournaments</option>
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}"
                                        {{ request('tournament_id') == $tournament->id ? 'selected' : '' }}>
                                        {{ $tournament->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-end space-x-2 pt-2">
                            <button type="submit" class="btn btn-primary w-full sm:w-auto">Filter</button>
                            <a href="{{ route('admin.actual-teams.index') }}"
                                class="btn btn-secondary w-full sm:w-auto">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            <!-- ======================================================= -->
            <!-- END: Filter Form Section -->
            <!-- ======================================================= -->

            {{-- Teams Table --}}
            <div
                class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 uppercase text-xs text-gray-700 dark:text-gray-300">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-4 py-3">Logo</th>
                                <th class="px-4 py-3">Team Name</th>
                                @if (auth()->user()->hasRole('Superadmin'))
                                    <th class="px-4 py-3">Organization</th>
                                @endif
                                <th class="px-4 py-3">Tournament</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($actualTeams as $team)
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-2">
                                        @if ($team->team_logo)
                                            <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }} Logo"
                                                class="h-10 w-10 object-cover rounded-full">
                                        @else
                                            <div
                                                class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs text-gray-500">
                                                No Logo
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-white">
                                        {{ $team->name }}
                                    </td>
                                    @if (auth()->user()->hasRole('Superadmin'))
                                        <td class="px-4 py-3">{{ $team->organization->name ?? '-' }}</td>
                                    @endif
                                    <td class="px-4 py-3">{{ $team->tournament->name ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end space-x-2">
                                            @can('actual-team.view')
                                                <a href="{{ route('admin.actual-teams.show', $team) }}" title="View"
                                                    class="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                        </path>
                                                    </svg>
                                                </a>
                                            @endcan
                                            @can('actual-team.edit')
                                                <a href="{{ route('admin.actual-teams.edit', $team) }}" title="Edit"
                                                    class="p-2 text-blue-500 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                                                    </svg>
                                                </a>
                                            @endcan
                                            @can('actual-team.delete')
                                                <form method="POST" action="{{ route('admin.actual-teams.destroy', $team) }}"
                                                    onsubmit="return confirm('Are you sure you want to delete this team?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Delete"
                                                        class="p-2 text-red-500 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- Dynamic colspan for the empty state --}}
                                    <td colspan="{{ auth()->user()->hasRole('Superadmin') ? '6' : '5' }}"
                                        class="px-4 py-6 text-center text-gray-500">
                                        {{ __('No teams found matching your criteria.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Pagination Links --}}
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $actualTeams->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
