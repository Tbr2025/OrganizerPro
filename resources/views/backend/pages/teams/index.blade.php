@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="flex justify-between items-center px-5">
                <h2 class="text-lg font-semibold">{{ __('Teams') }}</h2>
                <a href="{{ route('admin.teams.create') }}" class="btn-primary flex items-center gap-2">
                    <iconify-icon icon="feather:plus" height="16"></iconify-icon>
                    {{ __('Add Team') }}
                </a>
            </div>

            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900 uppercase text-xs text-gray-700 dark:text-gray-300">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-4 py-3">{{ __('Team Name') }}</th>
                                <th class="px-4 py-3">{{ __('Short Name') }}</th>
                                <th class="px-4 py-3">{{ __('Tournament') }}</th>
                                <th class="px-4 py-3">{{ __('Admin') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($teams as $team)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-3">{{ $team->name }}</td>
                                    <td class="px-4 py-3">{{ $team->short_name }}</td>
                                    <td class="px-4 py-3">{{ $team->tournament->name ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $team->admin->name ?? '-' }}</td>
                                    <td class="px-4 py-3 flex justify-end gap-2">
                                        <a href="{{ route('admin.teams.show', $team) }}" class="btn btn-sm btn-info">
                                            <iconify-icon icon="lucide:eye" height="16"></iconify-icon>
                                        </a>
                                        <form method="POST" action="{{ route('admin.teams.destroy', $team) }}" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <iconify-icon icon="lucide:trash" height="16"></iconify-icon>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-gray-500">
                                        {{ __('No teams found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="px-4 py-3">
                        {{ $teams->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
