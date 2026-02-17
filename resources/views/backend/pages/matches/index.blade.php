@extends('backend.layouts.app')

@section('title', 'Matches | ' . config('app.name'))

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[['name' => 'Matches']]" />
<div class="card p-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Matches</h2>
        <a href="{{ route('admin.matches.create') }}" class="btn-primary">+ New Match</a>
    </div>

  <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 overflow-x-auto overflow-y-visible">
        <table id="dataTable" class="w-full dark:text-gray-300">
            <thead class="bg-light text-capitalize">
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">#</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Tournament</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Match Date</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Team A</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Team B</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Status</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Winner</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Venue</th>
                    <th class="p-2 bg-gray-50 dark:bg-gray-800 dark:text-white text-left px-5">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matches as $match)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-4 sm:px-6">{{ $loop->iteration + ($matches->currentPage() - 1) * $matches->perPage() }}</td>
                        <td class="px-5 py-4 sm:px-6">{{ $match->tournament->name ?? '-' }}</td>
                        <td class="px-5 py-4 sm:px-6">{{ \Carbon\Carbon::parse($match->match_date)->format('d M Y') }}</td>
                        <td class="px-5 py-4 sm:px-6">{{ $match->teamA->name ?? '-' }}</td>
                        <td class="px-5 py-4 sm:px-6">{{ $match->teamB->name ?? '-' }}</td>
                        <td class="px-5 py-4 sm:px-6">
                            @if($match->is_cancelled)
                                <div>
                                    <span class="badge inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        Cancelled
                                    </span>
                                    @if($match->cancellation_reason)
                                        <p class="text-xs text-red-500 mt-1 max-w-[150px] truncate" title="{{ $match->cancellation_reason }}">{{ $match->cancellation_reason }}</p>
                                    @endif
                                </div>
                            @elseif($match->status === 'live')
                                <span class="badge inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-red-500 text-white">
                                    <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                    LIVE
                                </span>
                            @elseif($match->status === 'completed')
                                <span class="badge inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-white">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Completed
                                </span>
                            @else
                                <span class="badge inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-white">
                                    Upcoming
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 sm:px-6">{{ $match->winner->name ?? '-' }}</td>
                        <td class="px-5 py-4 sm:px-6">{{ $match->venue ?? '-' }}</td>
                        <td class="px-5 py-4 sm:px-6 flex justify-center">
                            <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                <x-buttons.action-item
                                    :href="route('admin.matches.show', $match)"
                                    icon="eye"
                                    :label="__('View')"
                                />
                                <x-buttons.action-item
                                    :href="route('admin.matches.edit', $match)"
                                    icon="pencil"
                                    :label="__('Edit')"
                                />
                                <x-buttons.action-item
                                    :href="route('admin.matches.appreciations.create', $match)"
                                    icon="star"
                                    :label="__('Add Appreciation')"
                                />
                                <x-buttons.action-item
                                    :href="route('admin.matches.summary.edit', $match)"
                                    icon="file-text"
                                    :label="__('Summary')"
                                />
                                @if(!$match->is_cancelled && $match->status !== 'live')
                                    <form action="{{ route('admin.matches.goLive', $match) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-green-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10,8 16,12 10,16"/></svg>
                                            {{ __('Go Live') }}
                                        </button>
                                    </form>
                                @endif
                                @if(!$match->is_cancelled && $match->status !== 'completed')
                                    <div x-data="{ cancelModalOpen: false }">
                                        <button @click="cancelModalOpen = true" type="button" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-orange-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            {{ __('Cancel Match') }}
                                        </button>

                                        <!-- Cancel Match Modal -->
                                        <div x-show="cancelModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                                            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
                                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Cancel Match</h3>
                                                <form action="{{ route('admin.matches.cancel', $match) }}" method="POST">
                                                    @csrf
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reason for cancellation</label>
                                                        <textarea name="cancellation_reason" rows="3" class="form-control w-full" placeholder="e.g., Rain, Ground not available, etc."></textarea>
                                                    </div>
                                                    <div class="flex gap-3">
                                                        <button type="button" @click="cancelModalOpen = false" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                                            No, Keep Match
                                                        </button>
                                                        <button type="submit" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                                                            Yes, Cancel Match
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (auth()->user()->can('match.delete'))
                                    <div x-data="{ deleteModalOpen: false }">
                                        <x-buttons.action-item
                                            type="modal-trigger"
                                            modal-target="deleteModalOpen"
                                            icon="trash"
                                            :label="__('Delete')"
                                            class="text-red-600 dark:text-red-400"
                                        />

                                        <x-modals.confirm-delete
                                            id="delete-modal-{{ $match->id }}"
                                            title="{{ __('Delete Match') }}"
                                            content="{{ __('Are you sure you want to delete this match?') }}"
                                            formId="delete-form-{{ $match->id }}"
                                            formAction="{{ route('admin.matches.destroy', $match) }}"
                                            modalTrigger="deleteModalOpen"
                                            cancelButtonText="{{ __('No, cancel') }}"
                                            confirmButtonText="{{ __('Yes, Confirm') }}"
                                        />
                                    </div>
                                @endif
                            </x-buttons.action-buttons>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <p class="text-gray-500 dark:text-gray-300">{{ __('No matches found') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="my-4 px-4 sm:px-6">
            {{ $matches->links() }}
        </div>
    </div>
</div>
@endsection
