@extends('backend.layouts.app')

@section('title', 'Registrations | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $tournament->name],
            ['label' => 'Registrations']
        ]" />

        <div class="mt-6">
            {{-- Public Registration Links --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Public Registration Links</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Share these links with players and teams to register for this tournament.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Player Registration Link --}}
                    <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">Player Registration</p>
                            <input type="text"
                                   readonly
                                   value="{{ route('public.tournament.register.player', $tournament->slug) }}"
                                   class="w-full text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded px-2 py-1 text-gray-600 dark:text-gray-400"
                                   id="player-reg-link">
                        </div>
                        <button type="button"
                                onclick="copyToClipboard('player-reg-link', this)"
                                class="flex-shrink-0 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition">
                            Copy
                        </button>
                    </div>

                    {{-- Team Registration Link --}}
                    <div class="flex items-center gap-2 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-purple-700 dark:text-purple-300 mb-1">Team Registration</p>
                            <input type="text"
                                   readonly
                                   value="{{ route('public.tournament.register.team', $tournament->slug) }}"
                                   class="w-full text-xs bg-white dark:bg-gray-800 border border-purple-200 dark:border-purple-700 rounded px-2 py-1 text-gray-600 dark:text-gray-400"
                                   id="team-reg-link">
                        </div>
                        <button type="button"
                                onclick="copyToClipboard('team-reg-link', this)"
                                class="flex-shrink-0 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition">
                            Copy
                        </button>
                    </div>
                </div>
            </div>

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Pending</p>
                            <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ $pendingCount }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-green-600 dark:text-green-400">Approved</p>
                            <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $approvedCount }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-red-600 dark:text-red-400">Rejected</p>
                            <p class="text-2xl font-bold text-red-900 dark:text-red-100">{{ $rejectedCount }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter Tabs --}}
            <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8">
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'status' => 'pending']) }}"
                        class="py-2 px-1 border-b-2 font-medium text-sm {{ request('status', 'pending') == 'pending' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}">
                        Pending
                    </a>
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'status' => 'approved']) }}"
                        class="py-2 px-1 border-b-2 font-medium text-sm {{ request('status') == 'approved' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}">
                        Approved
                    </a>
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'status' => 'rejected']) }}"
                        class="py-2 px-1 border-b-2 font-medium text-sm {{ request('status') == 'rejected' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}">
                        Rejected
                    </a>
                </nav>
            </div>

            {{-- Registrations Table --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl overflow-hidden">
                @if($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Name / Team</th>
                                    <th class="px-6 py-3">Contact</th>
                                    <th class="px-6 py-3">Type</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrations as $registration)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        {{-- Name / Team Column --}}
                                        <td class="px-6 py-4">
                                            @if($registration->type == 'team')
                                                <div class="flex items-center gap-3">
                                                    @if($registration->team_logo)
                                                        <img src="{{ Storage::url($registration->team_logo) }}" alt="Team Logo" class="w-10 h-10 rounded-lg object-cover">
                                                    @else
                                                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                                                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $registration->team_name }}</div>
                                                        @if($registration->team_short_name)
                                                            <div class="text-xs text-gray-500">({{ $registration->team_short_name }})</div>
                                                        @endif
                                                        <div class="text-xs text-gray-500">Captain: {{ $registration->captain_name }}</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-3">
                                                    @if($registration->player?->image_path)
                                                        <img src="{{ Storage::url($registration->player->image_path) }}" alt="Player" class="w-10 h-10 rounded-full object-cover">
                                                    @else
                                                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $registration->player->name ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Contact Column --}}
                                        <td class="px-6 py-4">
                                            @if($registration->type == 'team')
                                                <div class="text-xs space-y-1">
                                                    <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                        </svg>
                                                        {{ $registration->captain_email }}
                                                    </div>
                                                    <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                        </svg>
                                                        {{ $registration->captain_phone }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-xs space-y-1">
                                                    @if($registration->player?->email)
                                                        <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                            </svg>
                                                            {{ $registration->player->email }}
                                                        </div>
                                                    @endif
                                                    @if($registration->player?->mobile_number_full)
                                                        <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                            </svg>
                                                            {{ $registration->player->mobile_number_full }}
                                                        </div>
                                                    @endif
                                                    @if(!$registration->player?->email && !$registration->player?->mobile_number_full)
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Type Column --}}
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $registration->type == 'player' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                {{ ucfirst($registration->type) }}
                                            </span>
                                        </td>

                                        {{-- Status Column --}}
                                        <td class="px-6 py-4">
                                            @if($registration->status == 'pending')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Pending
                                                </span>
                                            @elseif($registration->status == 'approved')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Approved
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Rejected
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Date Column --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm">{{ $registration->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $registration->created_at->format('h:i A') }}</div>
                                        </td>

                                        {{-- Actions Column --}}
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                {{-- View Button --}}
                                                <a href="{{ route('admin.tournaments.registrations.show', [$tournament, $registration]) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View
                                                </a>

                                                @if($registration->status == 'pending')
                                                    <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                            Reject
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $registrations->links() }}
                    </div>
                @else
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No registrations</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No registrations found with the selected filter.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function copyToClipboard(inputId, btn) {
    const input = document.getElementById(inputId);
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.remove('bg-blue-600', 'bg-purple-600', 'hover:bg-blue-700', 'hover:bg-purple-700');
        btn.classList.add('bg-green-600');
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('bg-green-600');
            if (inputId === 'player-reg-link') {
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            } else {
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
            }
        }, 2000);
    });
}
</script>
@endpush
