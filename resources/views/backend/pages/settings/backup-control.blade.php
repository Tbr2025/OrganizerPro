@extends('backend.layouts.app')

@section('title')
{{ ucfirst($tab ?? 'Backups') . ' ' . __('Files') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
    {{-- Breadcrumbs Component --}}
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Optional: Hook for injecting content after breadcrumbs --}}
    {!! ld_apply_filters('settings_after_breadcrumbs', '') !!}

    <div class="space-y-6">
        {{-- Backup Files Section --}}
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Database Backups</h2>

                {{-- Check if backups exist --}}
                @if ($backups->isNotEmpty())
                <div class="mt-6 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle">
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">File Name</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Size</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Last Modified</th>
                                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                <span class="sr-only">Download</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900/50">
                                        {{-- Loop through each backup file --}}
                                        @foreach ($backups as $backup)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                                <div class="flex items-center">
                                                    <div class="ml-4">
                                                        <div class="font-medium text-gray-900 dark:text-white">{{ $backup['name'] }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ round($backup['size'] / 1024, 2) }} KB</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::createFromTimestamp($backup['last_modified'])->format('Y-m-d H:i') }}</td>
                                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                {{-- Download Link --}}
                                                <a href="{{ $backup['url'] }}" download class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Download<span class="sr-only">, {{ $backup['name'] }}</span></a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                {{-- Message if no backups are found --}}
                <div class="text-center py-10">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No backups available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">It seems there are no database backup files currently stored.</p>
                </div>
                @endif
            </div>
        </div>
        {{-- End Backup Files Section --}}

        {{-- Add other settings sections here if needed --}}

    </div>
</div>
@endsection