@extends('backend.layouts.app')

@section('title', 'Manage Teams - ' . $tournament->name . ' | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8" x-data="manageTeams()">

    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">{{ __('Manage Teams') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Retain players to teams and track budgets for {{ $tournament->name }}.</p>
        </div>
        <a href="{{ route('admin.tournaments.dashboard', $tournament) }}" class="btn btn-outline-secondary inline-flex items-center gap-2">
            <iconify-icon icon="lucide:arrow-left" width="16"></iconify-icon>
            Back to Dashboard
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Section A: Teams Budget Cards --}}
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Team Budgets</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
        @forelse ($teams as $team)
            @php
                $budget = $teamBudgets[$team->id] ?? ['total' => 0, 'retained' => 0, 'balance' => 0, 'retained_count' => 0];
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition">
                <div class="flex items-center gap-3 mb-4">
                    @if ($team->team_logo)
                        <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}"
                            class="h-10 w-10 object-cover rounded-lg ring-2 ring-gray-100 dark:ring-gray-700">
                    @else
                        <div class="h-10 w-10 rounded-lg ring-2 ring-gray-100 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                            <iconify-icon icon="lucide:shield" width="20" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ $team->name }}</h3>
                        @if ($budget['retained_count'] > 0)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-300">
                                {{ $budget['retained_count'] }} retained
                            </span>
                        @endif
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Total</span>
                        <span class="font-mono tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($budget['total'] / 1000000, 2) }}M</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Retained</span>
                        <span class="font-mono tabular-nums text-purple-600 dark:text-purple-400">{{ number_format($budget['retained'] / 1000000, 2) }}M</span>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-2 flex justify-between text-sm font-medium">
                        <span class="text-gray-500 dark:text-gray-400">Balance</span>
                        <span class="font-mono tabular-nums {{ $budget['balance'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($budget['balance'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500') }}">
                            {{ number_format($budget['balance'] / 1000000, 2) }}M
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">
                No teams found for this tournament.
            </div>
        @endforelse
    </div>

    {{-- Section B: Approved Players Table --}}
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Approved Players</h2>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-left">Player</th>
                        <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-left">Contact</th>
                        <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-left">Role</th>
                        <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-left">Status</th>
                        <th class="py-3 px-5 bg-gray-50/80 dark:bg-white/[0.03] text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($approvedPlayers as $player)
                        <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                            {{-- Player --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    @if ($player->photo)
                                        <img src="{{ Storage::url($player->photo) }}" alt="{{ $player->name }}"
                                            class="h-9 w-9 object-cover rounded-full ring-2 ring-gray-100 dark:ring-gray-700">
                                    @else
                                        <div class="h-9 w-9 rounded-full ring-2 ring-gray-100 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                                            <iconify-icon icon="lucide:user" width="16" class="text-gray-400"></iconify-icon>
                                        </div>
                                    @endif
                                    <span class="font-semibold text-gray-800 dark:text-white">{{ $player->name }}</span>
                                </div>
                            </td>

                            {{-- Contact --}}
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">
                                @if ($player->mobile)
                                    {{ substr($player->mobile, 0, 3) }}****{{ substr($player->mobile, -3) }}
                                @else
                                    -
                                @endif
                            </td>

                            {{-- Role --}}
                            <td class="px-5 py-3.5 text-gray-600 dark:text-gray-400">
                                {{ $player->playerType->name ?? '-' }}
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3.5">
                                @if ($player->player_mode === 'retained' && $player->actualTeam)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-500/20">
                                        <iconify-icon icon="lucide:lock" width="12"></iconify-icon>
                                        Retained by {{ $player->actualTeam->name }} ({{ number_format($player->retained_value / 1000000, 2) }}M)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
                                        <iconify-icon icon="lucide:circle-check" width="12"></iconify-icon>
                                        Available
                                    </span>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="px-5 py-3.5 text-right">
                                @if ($player->player_mode === 'retained')
                                    <form action="{{ route('admin.players.unretain', $player) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Remove retention for {{ $player->name }}?')">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-500/10 dark:hover:bg-red-500/20 transition">
                                            <iconify-icon icon="lucide:unlock" width="14"></iconify-icon>
                                            Unretain
                                        </button>
                                    </form>
                                @else
                                    <button type="button"
                                        @click="openRetainModal({{ $player->id }}, '{{ addslashes($player->name) }}')"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-500/10 dark:hover:bg-indigo-500/20 transition">
                                        <iconify-icon icon="lucide:lock" width="14"></iconify-icon>
                                        Retain
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                No approved players found for this tournament.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Retain Modal --}}
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 z-10"
            @click.outside="showModal = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Retain Player</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                Retaining <span class="font-medium text-gray-700 dark:text-gray-300" x-text="playerName"></span>
            </p>

            <form :action="retainUrl" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team</label>
                    <select name="actual_team_id" required
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select a team</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                    <input type="number" id="retain_amount_display" required min="0" step="any" placeholder="e.g. 5" value="5"
                        oninput="document.getElementById('retain_amount_raw').value = this.value ? Math.round(this.value * 1000000) : ''"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                    <input type="hidden" name="retained_value" id="retain_amount_raw" value="5000000">
                    <p class="text-xs text-gray-500 mt-1">Enter in millions (e.g. 5 = 50,00,000). Default: 5M.</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
                        Retain Player
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function manageTeams() {
    return {
        showModal: false,
        playerId: null,
        playerName: '',
        retainUrl: '',
        openRetainModal(id, name) {
            this.playerId = id;
            this.playerName = name;
            this.retainUrl = '{{ url("admin/players") }}/' + id + '/retain';
            this.showModal = true;
        }
    }
}
</script>
@endpush
@endsection
