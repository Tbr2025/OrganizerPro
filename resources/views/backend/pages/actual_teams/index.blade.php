@extends('backend.layouts.app')

@section('title', 'Teams | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
        <x-breadcrumbs :breadcrumbs="['title' => 'Teams']" />

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">{{ __('Teams') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage teams, budgets, and squad rosters.</p>
                </div>
                @can('actual-team.create')
                    <a href="{{ route('admin.actual-teams.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                        <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                        {{ __('Add New Team') }}
                    </a>
                @endcan
            </div>

            {{-- Filter Section --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-5">
                <form action="{{ route('admin.actual-teams.index') }}" method="GET">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">

                        {{-- Team Name Search --}}
                        <div>
                            <label for="search"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Team</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                placeholder="Team name..." class="form-control mt-1">
                        </div>

                        {{-- Organization Filter (Only for Superadmins) --}}
                        @if (auth()->user()->hasRole('Superadmin'))
                            <div>
                                <label for="organization_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
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
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
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
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn btn-primary inline-flex items-center gap-1.5">
                                <iconify-icon icon="lucide:search" width="15"></iconify-icon>
                                Filter
                            </button>
                            <a href="{{ route('admin.actual-teams.index') }}"
                                class="btn btn-secondary inline-flex items-center gap-1.5">
                                <iconify-icon icon="lucide:rotate-ccw" width="15"></iconify-icon>
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Teams Table --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Logo</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Team Name</th>
                                @if (auth()->user()->hasRole('Superadmin'))
                                    <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Organization</th>
                                @endif
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tournaments</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Spent</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Balance</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Squad</th>
                                <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($actualTeams as $team)
                                @php
                                    $budget = $teamBudgets[$team->id]['max_budget'] ?? 0;
                                    $spent = $teamBudgets[$team->id]['spent'] ?? 0;
                                    $balance = $budget - $spent;
                                    $userCount = $teamBudgets[$team->id]['user_count'] ?? 0;
                                    $squadMax = $teamBudgets[$team->id]['squad_max'] ?? 18;
                                    $squadPercent = $squadMax > 0 ? min(100, round(($userCount / $squadMax) * 100)) : 0;
                                @endphp
                                <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                                    {{-- Logo --}}
                                    <td class="px-5 py-3.5">
                                        @if ($team->team_logo)
                                            <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }} Logo"
                                                class="h-10 w-10 object-cover rounded-lg ring-2 ring-gray-100 dark:ring-gray-700">
                                        @else
                                            <div class="h-10 w-10 rounded-lg ring-2 ring-gray-100 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                                                <iconify-icon icon="lucide:shield" width="20" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Team Name --}}
                                    <td class="px-5 py-3.5 font-semibold text-gray-800 dark:text-white">
                                        {{ $team->name }}
                                    </td>

                                    {{-- Organization (Superadmin only) --}}
                                    @if (auth()->user()->hasRole('Superadmin'))
                                        <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">{{ $team->organization->name ?? '-' }}</td>
                                    @endif

                                    {{-- Tournaments --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex flex-wrap gap-1">
                                            @if ($team->is_global)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-500/20">
                                                    <iconify-icon icon="lucide:globe" width="12"></iconify-icon>
                                                    Global
                                                </span>
                                            @else
                                                @forelse ($team->tournaments as $t)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
                                                        {{ Str::limit($t->name, 20) }}
                                                    </span>
                                                @empty
                                                    @if ($team->tournament)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20">
                                                            {{ Str::limit($team->tournament->name, 20) }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                                    @endif
                                                @endforelse
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Budget / Total --}}
                                    <td class="px-5 py-3.5 font-mono text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ $teamBudgets[$team->id]['max_budget'] ?? '-' }}
                                    </td>

                                    {{-- Spent --}}
                                    <td class="px-5 py-3.5 font-mono text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ $teamBudgets[$team->id]['spent'] ?? '-' }}
                                    </td>

                                    {{-- Balance --}}
                                    <td class="px-5 py-3.5 font-mono text-sm tabular-nums font-medium {{ $balance > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400') }}">
                                        {{ $balance }}
                                    </td>

                                    {{-- Squad --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-300 {{ $squadPercent >= 100 ? 'bg-emerald-500' : ($squadPercent >= 50 ? 'bg-indigo-500' : 'bg-amber-500') }}"
                                                    style="width: {{ $squadPercent }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium tabular-nums text-gray-500 dark:text-gray-400">{{ $userCount }}/{{ $squadMax }}</span>
                                        </div>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end">
                                            <x-buttons.action-buttons :showLabel="false" align="right">
                                                @can('actual-team.view')
                                                    <x-buttons.action-item
                                                        :href="route('admin.actual-teams.show', $team)"
                                                        icon="lucide:eye"
                                                        label="View" />
                                                @endcan
                                                @can('actual-team.edit')
                                                    @if (in_array($team->id, $editableTeamIds))
                                                        <x-buttons.action-item
                                                            :href="route('admin.actual-teams.edit', $team)"
                                                            icon="lucide:pencil"
                                                            label="Edit" />
                                                    @endif
                                                @endcan
                                                @php
                                                    $teamManagerUser = $team->users->first(fn($u) => in_array($u->pivot->role, ['Owner', 'Manager', 'Team Manager']));
                                                @endphp
                                                @if ($teamManagerUser)
                                                    <x-buttons.action-item
                                                        :href="route('admin.users.login-as', $teamManagerUser->id)"
                                                        icon="lucide:log-in"
                                                        label="Login as Team" />
                                                @endif
                                                @can('actual-team.delete')
                                                    <form method="POST" action="{{ route('admin.auctions.clear', $team) }}"
                                                        onsubmit="return confirm('Are you sure you want to clear all auction data for this team? This cannot be undone.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-buttons.action-item
                                                            type="button"
                                                            icon="lucide:eraser"
                                                            label="Clear Auction Data"
                                                            class="text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30"
                                                            onclick="this.closest('form').submit()" />
                                                    </form>
                                                @endcan
                                                @can('actual-team.delete')
                                                    <form method="POST"
                                                        action="{{ route('admin.actual-teams.destroy', $team) }}"
                                                        onsubmit="return confirm('Are you sure you want to delete this team?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-buttons.action-item
                                                            type="button"
                                                            icon="lucide:trash-2"
                                                            label="Delete"
                                                            class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30"
                                                            onclick="this.closest('form').submit()" />
                                                    </form>
                                                @endcan
                                            </x-buttons.action-buttons>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()->hasRole('Superadmin') ? '9' : '8' }}"
                                        class="px-5 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
                                                <iconify-icon icon="lucide:shield" width="24" class="text-gray-400 dark:text-gray-500"></iconify-icon>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('No teams found') }}</p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('Try adjusting your filters or add a new team.') }}</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                @if ($actualTeams->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800">
                        {{ $actualTeams->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
