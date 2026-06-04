@extends('backend.layouts.app')

@section('title', 'Create Organization')

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="['title' => 'Create Organization', 'items' => [['label' => 'Organizations', 'url' => route('admin.organizations.index')]]]" />
    <div class="p-4 mx-auto md:p-6">
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

                {{-- Package & Limits (Superadmin only) --}}
                @hasrole('Superadmin')
                <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Package & Limits</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Package Type --}}
                        <div>
                            <label for="package_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Package Type</label>
                            <select name="package_type" id="package_type" class="form-control mt-1">
                                <option value="starter" {{ old('package_type') == 'starter' ? 'selected' : '' }}>Starter</option>
                                <option value="premium" {{ old('package_type') == 'premium' ? 'selected' : '' }}>Premium</option>
                                <option value="enterprise" {{ old('package_type') == 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                            </select>
                        </div>

                        {{-- Max Tournaments --}}
                        <div x-data="{ unlimited: {{ old('max_tournaments') ? 'false' : 'true' }} }">
                            <label for="max_tournaments" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Tournaments</label>
                            <div class="flex items-center gap-3 mt-1">
                                <input type="number" name="max_tournaments" id="max_tournaments" class="form-control flex-1"
                                    value="{{ old('max_tournaments') }}" min="1" placeholder="Unlimited"
                                    x-bind:disabled="unlimited">
                                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" x-model="unlimited"
                                        @change="if(unlimited) document.getElementById('max_tournaments').value = ''"
                                        class="rounded border-gray-300 dark:border-gray-600">
                                    Unlimited
                                </label>
                            </div>
                        </div>

                        {{-- Auction Enabled --}}
                        <div>
                            <label class="flex items-center gap-3 mt-6">
                                <input type="hidden" name="auction_enabled" value="0">
                                <input type="checkbox" name="auction_enabled" value="1"
                                    {{ old('auction_enabled') ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Auction Enabled</span>
                            </label>
                        </div>

                        {{-- Auction Modes --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Auction Modes</label>
                            <div class="flex flex-wrap gap-4">
                                @foreach (['open' => 'Open Bid', 'closed' => 'Closed Bid', 'offline' => 'Offline'] as $mode => $label)
                                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <input type="checkbox" name="auction_modes[]" value="{{ $mode }}"
                                            {{ in_array($mode, old('auction_modes', [])) ? 'checked' : '' }}
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endhasrole

                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('admin.organizations.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Organization</button>
                </div>
            </div>
        </form>
    </div>
@endsection
