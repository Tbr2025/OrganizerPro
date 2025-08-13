@extends('backend.layouts.app')

@section('title', 'Organizations')

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-semibold">Organizations</h1>
            @can('organization.create')
                <a href="{{ route('admin.organizations.create') }}" class="btn btn-primary">
                    Create New Organization
                </a>
            @endcan
        </div>

        <div class="mt-4 bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3">Created At</th>
                        <th scope="col" class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($organizations as $organization)
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $organization->name }}</td>
                            <td class="px-6 py-4">{{ $organization->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center space-x-4">
                                    @can('organization.edit')
                                        <a href="{{ route('admin.organizations.edit', $organization->id) }}"
                                            class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                    @endcan
                                    @can('organization.delete')
                                        <form action="{{ route('admin.organizations.destroy', $organization->id) }}"
                                            method="POST" onsubmit="return confirm('Are you sure?')">
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
                            <td colspan="3" class="px-6 py-4 text-center">No organizations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $organizations->links() }}
        </div>
    </div>
@endsection
