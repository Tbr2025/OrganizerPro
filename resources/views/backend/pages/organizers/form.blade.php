@extends('backend.layouts.app')

@section('title', ($organizer->exists ? 'Edit' : 'New') . ' Organizer | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-5xl md:p-6" x-data="{ userMode: 'existing' }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
    <x-messages />

    <form method="POST" action="{{ $organizer->exists ? route('admin.organizers.update', $organizer) : route('admin.organizers.store') }}"
          class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5 sm:p-6 space-y-6">
        @csrf
        @if($organizer->exists) @method('PUT') @endif

        {{-- Who --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Organizer</h3>
            @if($organizer->exists)
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $organizer->name }}</span>
                    <span class="text-gray-400">· {{ $organizer->email }}</span>
                </div>
            @else
                <div class="flex gap-4 mb-3 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="user_mode" value="existing" x-model="userMode"> Pick existing user</label>
                    <label class="flex items-center gap-2"><input type="radio" name="user_mode" value="new" x-model="userMode"> Create new user</label>
                </div>
                <div x-show="userMode === 'existing'" class="max-w-md">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Existing organizer user</label>
                    <select name="user_id" class="form-control">
                        <option value="">— Select a user —</option>
                        @foreach($existingOrganizers as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Only users who already have the Organizer role are listed.</p>
                </div>
                <div x-show="userMode === 'new'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="Organizer name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="organizer@example.com">
                    </div>
                    <p class="sm:col-span-2 text-xs text-gray-500">A login password is generated and emailed to them automatically.</p>
                </div>
            @endif
        </div>

        {{-- Assignments --}}
        @php
            $sel = fn($id, $arr) => in_array($id, $arr, true) ? 'checked' : '';
            $box = 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
        @endphp

        <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Tournaments</h4>
            @if($tournaments->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($tournaments as $t)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer hover:border-indigo-400">
                    <input type="checkbox" name="tournaments[]" value="{{ $t->id }}" {{ $sel($t->id, $assignedTournamentIds) }} class="{{ $box }}">
                    <span class="truncate">{{ $t->name }}</span>
                </label>
                @endforeach
            </div>
            @else<p class="text-sm text-gray-500">No tournaments available.</p>@endif
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Teams</h4>
            @if($teams->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                @foreach($teams as $t)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer hover:border-indigo-400">
                    <input type="checkbox" name="teams[]" value="{{ $t->id }}" {{ $sel($t->id, $assignedTeamIds) }} class="{{ $box }}">
                    <span class="truncate">{{ $t->name }}</span>
                </label>
                @endforeach
            </div>
            @else<p class="text-sm text-gray-500">No teams available.</p>@endif
        </div>

        <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Matches</h4>
            @if($matches->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                @foreach($matches as $m)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer hover:border-indigo-400">
                    <input type="checkbox" name="matches[]" value="{{ $m->id }}" {{ $sel($m->id, $assignedMatchIds) }} class="{{ $box }}">
                    <span class="truncate">{{ $m->name ?? ('Match #' . $m->id) }}</span>
                </label>
                @endforeach
            </div>
            @else<p class="text-sm text-gray-500">No matches available.</p>@endif
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-brand-500 text-white hover:bg-brand-600">Save &amp; Notify Organizer</button>
            <a href="{{ route('admin.organizers.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Cancel</a>
        </div>
    </form>
</div>
@endsection
