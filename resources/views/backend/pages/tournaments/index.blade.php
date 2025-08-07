@extends('backend.layouts.app')

@section('title', 'Tournaments | ' . config('app.name'))

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="$breadcrumbs">
        <x-slot name="title_after">
            @if (request('search'))
                <span
                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full">
                    Search: {{ request('search') }}
                </span>
            @endif
        </x-slot>
    </x-breadcrumbs>

    <div class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tournaments"
                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-blue-500" />
            <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
                Search
            </button>
        </form>

        <a href="{{ route('admin.tournaments.create') }}"
            class="inline-flex items-center px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700">
            + Add Tournament
        </a>
    </div>

    <div class="overflow-x-auto bg-white border rounded-md shadow-sm">
        <table class="min-w-full text-sm text-left text-gray-800">
            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Start Date</th>
                    <th class="px-4 py-3">End Date</th>
                    <th class="px-4 py-3">Location</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tournaments as $tournament)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3">
                            {{ $loop->iteration + ($tournaments->currentPage() - 1) * $tournaments->perPage() }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $tournament->name }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($tournament->start_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($tournament->end_date)->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $tournament->location ?? '—' }}</td>
                        <td class="px-4 py-3 flex items-center space-x-2">
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                                class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                            <form action="{{ route('admin.tournaments.destroy', $tournament) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this tournament?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-5 text-center text-gray-500">
                            No tournaments found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">
            {{ $tournaments->withQueryString()->links() }}
        </div>
    </div>
@endsection
