@extends('backend.layouts.app')

@section('title', 'Create Actual Team | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 max-w-2xl mx-auto">
        <h1 class="text-xl font-semibold mb-6">Create Actual Team</h1>

        <form action="{{ route('admin.actual-teams.store') }}" method="POST" class="space-y-6">
            @csrf

            <div>
                <label for="organization_id" class="block font-medium text-gray-700">Organization</label>
                <select id="organization_id" name="organization_id" required
                    class="mt-1 block w-full rounded border-gray-300 @error('organization_id') border-red-500 @enderror">
                    <option value="">Select Organization</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>
                            {{ $org->name }}</option>
                    @endforeach
                </select>
                @error('organization_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="tournament_id" class="block font-medium text-gray-700">Tournament</label>
                <select id="tournament_id" name="tournament_id" required
                    class="mt-1 block w-full rounded border-gray-300 @error('tournament_id') border-red-500 @enderror">
                    <option value="">Select Tournament</option>
                    @foreach ($tournaments as $t)
                        <option value="{{ $t->id }}" {{ old('tournament_id') == $t->id ? 'selected' : '' }}>
                            {{ $t->name }}</option>
                    @endforeach
                </select>
                @error('tournament_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="name" class="block font-medium text-gray-700">Team Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full rounded border-gray-300 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Team</button>
            </div>
        </form>
    </div>
@endsection
