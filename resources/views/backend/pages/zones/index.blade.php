@extends('backend.layouts.app')

@section('title', 'Zones')

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Zones</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Manage zones for organizing tournaments</p>
            </div>
            @can('zone.create')
                <a href="{{ route('admin.zones.create') }}" class="btn btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create New Zone
                </a>
            @endcan
        </div>

        <div class="mt-4 bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Zone</th>
                        @if(auth()->user()->hasRole('Superadmin'))
                            <th scope="col" class="px-6 py-3">Organization</th>
                        @endif
                        <th scope="col" class="px-6 py-3">Tournaments</th>
                        <th scope="col" class="px-6 py-3">Status</th>
                        <th scope="col" class="px-6 py-3">Created At</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($zone->logo)
                                        <img src="{{ asset('storage/' . $zone->logo) }}" alt="{{ $zone->name }}" class="w-10 h-10 rounded-lg object-cover">
                                    @else
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                                            {{ strtoupper(substr($zone->name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $zone->name }}</div>
                                        @if($zone->description)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ Str::limit($zone->description, 50) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            @if(auth()->user()->hasRole('Superadmin'))
                                <td class="px-6 py-4">{{ $zone->organization->name ?? 'N/A' }}</td>
                            @endif
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ $zone->tournaments->count() }} tournaments
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($zone->status === 'active')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $zone->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center space-x-4">
                                    @can('zone.edit')
                                        <a href="{{ route('admin.zones.edit', $zone->id) }}"
                                            class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                    @endcan
                                    @can('zone.delete')
                                        <form action="{{ route('admin.zones.destroy', $zone->id) }}"
                                            method="POST" onsubmit="return confirm('Are you sure you want to delete this zone?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasRole('Superadmin') ? 6 : 5 }}" class="px-6 py-8 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <p class="text-gray-500 dark:text-gray-400">No zones found.</p>
                                    @can('zone.create')
                                        <a href="{{ route('admin.zones.create') }}" class="mt-2 text-blue-600 hover:underline">Create your first zone</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $zones->links() }}
        </div>
    </div>
@endsection
