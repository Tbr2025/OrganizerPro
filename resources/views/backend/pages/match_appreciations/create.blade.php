@extends('backend.layouts.app')

@section('title', 'Add Match Appreciation | ' . config('app.name'))

@section('admin-content')
    @php
        $breadcrumbs = [
            ['label' => 'Matches', 'url' => route('admin.matches.index')],
            ['label' => $match->title ?? 'Match Details', 'url' => route('admin.matches.show', $match)],
            ['label' => 'Add Appreciation'],
        ];
    @endphp

    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="bg-white p-6 shadow rounded-md max-w-2xl mx-auto">
        <h2 class="text-xl font-semibold mb-4">Add Match Appreciation</h2>

        <form action="{{ route('admin.matches.appreciations.store', $match) }}" method="POST" enctype="multipart/form-data"
            class="space-y-4">
            @csrf

            <!-- Player Selection -->
            <div>
                <label for="player_id" class="block text-sm font-medium text-gray-700">Player</label>
                <select name="player_id" id="player_id" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-blue-300">
                    <option value="">Select a Player</option>
                    @foreach ($players as $player)
                        <option value="{{ $player->id }}" {{ old('player_id') == $player->id ? 'selected' : '' }}>
                            {{ $player->name }}
                        </option>
                    @endforeach
                </select>
                @error('player_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Title Selection -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Award Title</label>
                <select name="title" id="title" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-blue-300">
                    <option value="">Select Title</option>
                    @foreach ($titles as $title)
                        <option value="{{ $title }}" {{ old('title') == $title ? 'selected' : '' }}>
                            {{ $title }}
                        </option>
                    @endforeach
                </select>
                @error('title')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Image Upload -->
            <div>
                <label for="image" class="block text-sm font-medium text-gray-700">Optional Image</label>
                <input type="file" name="image" id="image"
                    class="mt-1 block w-full text-sm text-gray-500 file:border file:border-gray-300 file:rounded file:px-4 file:py-2 file:bg-gray-100 hover:file:bg-gray-200">
                @error('image')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit -->
            <div class="pt-4">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition">
                    Save Appreciation
                </button>
                <a href="{{ route('admin.matches.show', $match) }}"
                    class="ml-2 text-sm text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
