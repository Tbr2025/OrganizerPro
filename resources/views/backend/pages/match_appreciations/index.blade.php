@extends('backend.layouts.app')

@section('title', 'Match Appreciations | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Match Appreciations</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Awards given to players for outstanding performance</p>
                </div>
            </div>

            {{-- Info Box --}}
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-blue-800 dark:text-blue-300 font-medium">How to add appreciations?</p>
                        <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                            Go to <a href="{{ route('admin.matches.index') }}" class="underline font-semibold">Matches</a> →
                            View a match → Click <strong>"Add Appreciation"</strong> button to award players.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                        <tr>
                            <th class="p-3 text-left">Match</th>
                            <th class="p-3 text-left">Player</th>
                            <th class="p-3 text-left">Title</th>
                            <th class="p-3 text-left">Image</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-700 dark:text-gray-300">
                        @forelse ($appreciations as $appreciation)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="p-3 whitespace-nowrap">{{ $appreciation->match->name ?? '—' }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $appreciation->player->user->name ?? '—' }}</td>
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
                                        action="{{ route('admin.matches.appreciations.destroy', $appreciation) }}"
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
                                <td colspan="5" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                    </svg>
                                    <p class="font-medium">No appreciations found</p>
                                    <p class="text-sm mt-1">Go to a match and add appreciations for players.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($appreciations->hasPages())
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        {{ $appreciations->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
