@extends('backend.layouts.app')

@section('title')
{{ ucfirst($tab ?? 'Backups') . ' ' . __('Files') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {!! ld_apply_filters('settings_after_breadcrumbs', '') !!}

    <div class="space-y-6">
        {{-- Header with Create Backup Button --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Database Backups</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Create, download, restore, export, and import database backups</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('admin.backups.export') }}"
                   class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition text-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Database
                </a>
                <a href="{{ route('admin.backups.export', ['compress' => 1]) }}"
                   class="inline-flex items-center justify-center px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-medium rounded-lg transition text-sm"
                   title="Download a gzip-compressed .sql.gz dump — much smaller and faster">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export (.sql.gz)
                </a>
                <form action="{{ route('admin.backups.create') }}" method="POST">
                    @csrf
                    <button type="submit" id="createBackupBtn" onclick="this.disabled=true; this.innerHTML='<svg class=\'w-5 h-5 mr-2 animate-spin\' fill=\'none\' viewBox=\'0 0 24 24\'><circle class=\'opacity-25\' cx=\'12\' cy=\'12\' r=\'10\' stroke=\'currentColor\' stroke-width=\'4\'></circle><path class=\'opacity-75\' fill=\'currentColor\' d=\'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z\'></path></svg> Creating...'; this.form.submit();"
                            class="inline-flex items-center justify-center w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Create Backup
                    </button>
                </form>
            </div>
        </div>

        {{-- Backup Files Table --}}
        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if ($backups->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">File Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Size</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                            <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($backups as $backup)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $backup['name'] }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if($backup['size'] >= 1048576)
                                    {{ round($backup['size'] / 1048576, 2) }} MB
                                @else
                                    {{ round($backup['size'] / 1024, 2) }} KB
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::createFromTimestamp($backup['last_modified'])->format('M d, Y h:i A') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Download --}}
                                    <a href="{{ route('admin.backups.download', ['file' => $backup['name']]) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>

                                    {{-- Restore --}}
                                    <form action="{{ route('admin.backups.restore') }}" method="POST" class="inline"
                                          onsubmit="return confirm('Are you sure you want to restore from this backup?\n\nFile: {{ $backup['name'] }}\n\nThis will REPLACE the current database. A safety backup will be created first.');">
                                        @csrf
                                        <input type="hidden" name="file" value="{{ $backup['name'] }}">
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-amber-600 bg-amber-50 hover:bg-amber-100 dark:text-amber-400 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            Restore
                                        </button>
                                    </form>

                                    {{-- Delete --}}
                                    <form action="{{ route('admin.backups.delete') }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete backup {{ $backup['name'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="file" value="{{ $backup['name'] }}">
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-red-600 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-16">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                </svg>
                <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No backups yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create your first database backup using the button above.</p>
            </div>
            @endif
        </div>

        {{-- Import Database (Caution) --}}
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="ml-3 w-full">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">Import Database — Use With Caution</p>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">Uploading and importing a <code>.sql</code> or <code>.sql.gz</code> file will <strong>replace data</strong> in the current database. A safety backup is created automatically before the import runs. Max file size: 1&nbsp;GB. For large databases, prefer the compressed <code>.sql.gz</code> export — it uploads much faster.</p>
                    <form action="{{ route('admin.backups.import') }}" method="POST" enctype="multipart/form-data" class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3"
                          onsubmit="return confirm('⚠ CAUTION: Importing will REPLACE data in the current database with the contents of the uploaded file. A safety backup will be created first. Continue?');">
                        @csrf
                        <input type="file" name="sql_file" accept=".sql,.gz,.sql.gz" required
                               class="block text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-red-100 file:text-red-700 hover:file:bg-red-200 dark:file:bg-red-900/40 dark:file:text-red-300 cursor-pointer">
                        <button type="submit"
                                class="inline-flex items-center justify-center px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition text-sm whitespace-nowrap">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Import Database
                        </button>
                    </form>
                    @error('sql_file')
                        <p class="mt-2 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Info Note --}}
        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="ml-3 text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-medium">About Restore</p>
                    <p class="mt-1">Restoring a backup will replace the current database with the backup data. A safety backup is automatically created before each restore operation.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
