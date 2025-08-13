@extends('backend.layouts.app')

@section('title', 'Create Organization')

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6">
        <h1 class="text-xl font-semibold mb-4">Create New Organization</h1>
        <form action="{{ route('admin.organizations.store') }}" method="POST">
            @csrf
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" id="name" class="form-control mt-1"
                            value="{{ old('name') }}" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('admin.organizations.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Organization</button>
                </div>
            </div>
        </form>
    </div>
@endsection
