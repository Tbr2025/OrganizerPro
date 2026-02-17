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
                            <span class="badge inline-flex items-center justify-center px-2 py-1 text-xs font-medium rounded-full
                                {{ $match->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-white' :
                                   ($match->status === 'live' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-white' :
                                   'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-white') }}">
                                {{ ucfirst($match->status) }}
                            </span>
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
