@extends('backend.layouts.app')

@section('title', 'Registration Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $tournament->name],
            ['label' => 'Registrations', 'url' => route('admin.tournaments.registrations.index', $tournament)],
            ['label' => 'Details']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <div class="flex justify-between items-start mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Registration Details</h2>
                @if($registration->status == 'pending')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        Pending
                    </span>
                @elseif($registration->status == 'approved')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Approved
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        Rejected
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Player Name</h3>
                    <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $registration->player->name ?? 'N/A' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</h3>
                    <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $registration->player->email ?? 'N/A' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Registration Type</h3>
                    <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ ucfirst($registration->type) }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Team</h3>
                    <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $registration->actualTeam->name ?? '-' }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Registration Date</h3>
                    <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $registration->created_at->format('M d, Y H:i') }}</p>
                </div>

                @if($registration->processedBy)
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Processed By</h3>
                        <p class="mt-1 text-lg text-gray-900 dark:text-white">{{ $registration->processedBy->name }}</p>
                    </div>
                @endif
            </div>

            @if($registration->status == 'pending')
                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-4">
                    <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700">
                            Reject
                        </button>
                    </form>
                    <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700">
                            Approve
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
