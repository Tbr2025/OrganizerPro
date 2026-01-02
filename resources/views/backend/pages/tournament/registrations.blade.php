@extends('backend.layouts.app')

@section('title', 'Registrations | ' . $tournament->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Tournament Registrations</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tournament->name }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
                <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => 'player']) }}"
                   class="inline-block p-4 border-b-2 rounded-t-lg {{ $type === 'player' ? 'text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    Players ({{ $registrations->where('registration_type', 'player')->count() }})
                </a>
            </li>
            <li class="mr-2">
                <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => 'team']) }}"
                   class="inline-block p-4 border-b-2 rounded-t-lg {{ $type === 'team' ? 'text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300' }}">
                    Teams ({{ $registrations->where('registration_type', 'team')->count() }})
                </a>
            </li>
        </ul>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-4 mb-6">
        <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => $type, 'status' => 'pending']) }}"
           class="px-4 py-2 rounded-lg {{ $status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
            Pending
        </a>
        <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => $type, 'status' => 'approved']) }}"
           class="px-4 py-2 rounded-lg {{ $status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
            Approved
        </a>
        <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => $type, 'status' => 'rejected']) }}"
           class="px-4 py-2 rounded-lg {{ $status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
            Rejected
        </a>
        <a href="{{ route('admin.tournaments.registrations', [$tournament, 'type' => $type]) }}"
           class="px-4 py-2 rounded-lg {{ !$status ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
            All
        </a>
    </div>

    {{-- Registrations Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            {{ $type === 'player' ? 'Player' : 'Team' }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Contact
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($filteredRegistrations as $registration)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $registration->data['name'] ?? $registration->data['team_name'] ?? 'N/A' }}
                                </div>
                                @if($type === 'player' && isset($registration->data['jersey_name']))
                                    <div class="text-sm text-gray-500">"{{ $registration->data['jersey_name'] }}"</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $registration->data['email'] ?? $registration->data['captain_email'] ?? 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $registration->data['mobile_number'] ?? $registration->data['captain_phone'] ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($registration->status === 'pending')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                        Pending
                                    </span>
                                @elseif($registration->status === 'approved')
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        Approved
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $registration->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($registration->status === 'pending')
                                    <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900 mr-3">
                                            Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            Reject
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                No registrations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if(method_exists($filteredRegistrations, 'links'))
        <div class="mt-6">
            {{ $filteredRegistrations->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
