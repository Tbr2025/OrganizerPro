@extends('backend.layouts.app')

@section('title', 'Organizers | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-6xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-4 sm:px-6 flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Organizers</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Create or pick organizer users and assign them tournaments, teams and matches. They're emailed their access.</p>
            </div>
            <a href="{{ route('admin.organizers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-brand-500 text-white hover:bg-brand-600 whitespace-nowrap">+ New / Assign Organizer</a>
        </div>

        <div class="p-4 sm:p-6 overflow-x-auto">
            @if($organizers->count())
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
                        <th class="py-2 pr-4">Organizer</th>
                        <th class="py-2 px-3 text-center">Tournaments</th>
                        <th class="py-2 px-3 text-center">Teams</th>
                        <th class="py-2 px-3 text-center">Matches</th>
                        <th class="py-2 pl-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($organizers as $o)
                    <tr>
                        <td class="py-3 pr-4">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $o->name }}</div>
                            <div class="text-xs text-gray-400">{{ $o->email }}</div>
                        </td>
                        <td class="py-3 px-3 text-center">{{ $o->assigned_tournaments_count }}</td>
                        <td class="py-3 px-3 text-center">{{ $o->assigned_teams_count }}</td>
                        <td class="py-3 px-3 text-center">{{ $o->assigned_matches_count }}</td>
                        <td class="py-3 pl-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.organizers.edit', $o) }}" class="text-indigo-600 hover:underline text-xs font-medium">Edit / Assign</a>
                            <form action="{{ route('admin.organizers.destroy', $o) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Remove all assignments for {{ $o->name }}? (The user account is kept.)')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline text-xs font-medium">Remove access</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $organizers->links() }}</div>
            @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No organizers yet. Click <strong>New / Assign Organizer</strong> to add one.</p>
            @endif
        </div>
    </div>
</div>
@endsection
