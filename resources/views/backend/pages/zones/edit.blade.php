@extends('backend.layouts.app')

@section('title', 'Edit Zone')

@section('admin-content')
    <div class="p-4 mx-auto max-w-2xl md:p-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Edit Zone</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Update zone details</p>
        </div>

        <form action="{{ route('admin.zones.update', $zone->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="space-y-6">
                    {{-- Organization Selection --}}
                    <div>
                        <label for="organization_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Organization <span class="text-red-500">*</span>
                        </label>
                        <select name="organization_id" id="organization_id" class="form-control mt-1" required>
                            <option value="">Select Organization</option>
                            @foreach($organizations as $organization)
                                <option value="{{ $organization->id }}" {{ old('organization_id', $zone->organization_id) == $organization->id ? 'selected' : '' }}>
                                    {{ $organization->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('organization_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Zone Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Zone Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" class="form-control mt-1"
                            value="{{ old('name', $zone->name) }}" placeholder="e.g., North Zone, South Zone" required>
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea name="description" id="description" rows="3" class="form-control mt-1"
                            placeholder="Brief description of this zone">{{ old('description', $zone->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Current Logo --}}
                    @if($zone->logo)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Current Logo
                            </label>
                            <img src="{{ asset('storage/' . $zone->logo) }}" alt="{{ $zone->name }}" class="w-20 h-20 rounded-lg object-cover">
                        </div>
                    @endif

                    {{-- Logo --}}
                    <div>
                        <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $zone->logo ? 'Change Logo' : 'Zone Logo' }}
                        </label>
                        <input type="file" name="logo" id="logo" class="form-control mt-1" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1">Recommended: Square image, max 2MB</p>
                        @error('logo')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Status --}}
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" id="status" class="form-control mt-1" required>
                                <option value="active" {{ old('status', $zone->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $zone->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Order --}}
                        <div>
                            <label for="order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Display Order
                            </label>
                            <input type="number" name="order" id="order" class="form-control mt-1"
                                value="{{ old('order', $zone->order) }}" min="0" placeholder="0">
                            <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                            @error('order')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Zone Statistics --}}
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Zone Statistics</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Tournaments</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $zone->tournaments()->count() }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Created</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $zone->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('admin.zones.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Zone</button>
                </div>
            </div>
        </form>
    </div>
@endsection
