@extends('backend.layouts.app')

@section('title', 'Edit Organization')

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="['title' => 'Edit Organization', 'items' => [['label' => 'Organizations', 'url' => route('admin.organizations.index')]]]" />
    <div class="p-4 mx-auto md:p-6">
        <form action="{{ route('admin.organizations.update', $organization->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" id="name" class="form-control mt-1"
                            value="{{ old('name', $organization->name) }}" required>
                    </div>
                </div>

                {{-- Credits (Superadmin only). Everything is paid per feature; there are no package limits. --}}
                @hasrole('Superadmin')
                <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">{{ app(\App\Services\Credits\CreditService::class)->term() }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Tournaments, auctions, live scoring, the LED wall and posters are paid per use from this organization's wallet. Prices are set in Credits → Manage.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ $balance }}</p>
                            <a href="{{ route('admin.credits.index', ['org' => $organization->id]) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Open wallet</a>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tournaments</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ $tournamentCount }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Auctions</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ $auctionCount }}</p>
                        </div>
                    </div>
                </div>
                @endhasrole

                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('admin.organizations.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Organization</button>
                </div>
            </div>
        </form>
    </div>
@endsection
