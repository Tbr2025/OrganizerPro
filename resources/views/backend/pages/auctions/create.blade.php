@extends('backend.layouts.app')

@section('title', 'Create Auction | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-screen-xl md:p-6">
    <x-breadcrumbs :breadcrumbs="[['label' => 'Auctions', 'url' => route('admin.auctions.index')], ['label' => 'Create']]" />

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <form action="{{ route('admin.auctions.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Auction Name --}}
            <div>
                <label for="name" class="block text-sm font-medium">Auction Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Tournament ID --}}
            <div>
                <label for="tournament_id" class="block text-sm font-medium">Tournament</label>
                <select name="tournament_id" id="tournament_id" class="mt-1 block w-full rounded-lg border-gray-300">
                    <option value="">-- Select Tournament --</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>
                            {{ $tournament->name }}
                        </option>
                    @endforeach
                </select>
                @error('tournament_id')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Organization --}}
            <div>
                <label for="organization_id" class="block text-sm font-medium">Organization</label>
                <select name="organization_id" id="organization_id" class="mt-1 block w-full rounded-lg border-gray-300">
                    <option value="">-- Select Organization --</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
                @error('organization_id')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Start Date & Time --}}
            <div>
                <label for="start_at" class="block text-sm font-medium">Start Date & Time</label>
                <input type="datetime-local" name="start_at" id="start_at" value="{{ old('start_at') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                @error('start_at')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- End Date & Time --}}
            <div>
                <label for="end_at" class="block text-sm font-medium">End Date & Time</label>
                <input type="datetime-local" name="end_at" id="end_at" value="{{ old('end_at') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                @error('end_at')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Base Price --}}
            <div>
                <label for="base_price" class="block text-sm font-medium">Base Price (in Millions)</label>
                <input type="number" step="0.01" name="base_price" id="base_price" value="{{ old('base_price', 1.0) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                @error('base_price')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Max Bid per Player --}}
            <div>
                <label for="max_bid_per_player" class="block text-sm font-medium">Max Bid per Player (in Millions)</label>
                <input type="number" step="0.01" name="max_bid_per_player" id="max_bid_per_player"
                       value="{{ old('max_bid_per_player', 6.0) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                @error('max_bid_per_player')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Max Budget per Team --}}
            <div>
                <label for="max_budget_per_team" class="block text-sm font-medium">Max Budget per Team (in Millions)</label>
                <input type="number" step="0.01" name="max_budget_per_team" id="max_budget_per_team"
                       value="{{ old('max_budget_per_team', 100.0) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                @error('max_budget_per_team')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Status --}}
            <div>
                <label for="status" class="block text-sm font-medium">Status</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['scheduled', 'running', 'paused', 'completed'] as $status)
                        <option value="{{ $status }}" {{ old('status', 'scheduled') == $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
                @error('status')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>

            {{-- Submit --}}
            <div class="flex justify-end">
                <x-buttons.submit-buttons :create="true" />
            </div>
        </form>
    </div>
</div>
@endsection
