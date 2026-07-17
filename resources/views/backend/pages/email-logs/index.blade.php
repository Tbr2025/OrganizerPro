@extends('backend.layouts.app')

@section('title')
{{ __('Email Logs - ' . config('app.name')) }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6" x-data="emailLogPage()">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">

            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <a href="{{ route('admin.email-logs.index') }}"
                    class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-white/[0.03] hover:shadow transition">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</p>
                </a>
                <a href="{{ route('admin.email-logs.index', ['status' => 'sent']) }}"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20 hover:shadow transition">
                    <p class="text-sm text-green-600 dark:text-green-400">{{ __('Sent') }}</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ number_format($stats['sent']) }}</p>
                </a>
                <a href="{{ route('admin.email-logs.index', ['status' => 'failed']) }}"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20 hover:shadow transition">
                    <p class="text-sm text-red-600 dark:text-red-400">{{ __('Failed') }}</p>
                    <p class="text-2xl font-bold text-red-700 dark:text-red-300">{{ number_format($stats['failed']) }}</p>
                </a>
                <a href="{{ route('admin.email-logs.index', ['status' => 'bounced']) }}"
                    class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20 hover:shadow transition">
                    <p class="text-sm text-orange-600 dark:text-orange-400">{{ __('Bounced') }}</p>
                    <p class="text-2xl font-bold text-orange-700 dark:text-orange-300">{{ number_format($stats['bounced']) }}</p>
                </a>
            </div>

            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-col md:flex-row justify-between items-center gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Email Logs') }}</h3>
                    <div class="flex items-center gap-2">
                        @if($stats['total'] > 0)
                            <form action="{{ route('admin.email-logs.clear') }}" method="POST"
                                onsubmit="return confirm('{{ __('Are you sure you want to delete all email logs? This action cannot be undone.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                                    {{ __('Clear All Logs') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Filter Bar --}}
                <div class="px-5 py-3 sm:px-6 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                    <form action="{{ route('admin.email-logs.index') }}" method="GET"
                        class="flex flex-col sm:flex-row flex-wrap items-end gap-3">
                        <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[180px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Search (Email / Subject)') }}</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="{{ __('Search...') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[130px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Status') }}</label>
                            <select name="status" class="form-control text-sm">
                                <option value="">{{ __('All') }}</option>
                                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ __('Sent') }}</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                                <option value="bounced" {{ request('status') === 'bounced' ? 'selected' : '' }}>{{ __('Bounced') }}</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[160px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Mailable Type') }}</label>
                            <select name="mailable_class" class="form-control text-sm">
                                <option value="">{{ __('All Types') }}</option>
                                @foreach($mailableTypes as $type)
                                    <option value="{{ $type }}" {{ request('mailable_class') === $type ? 'selected' : '' }}>
                                        {{ class_basename($type) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[140px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Date From') }}</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[140px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Date To') }}</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[130px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Group') }}</label>
                            <select name="group" class="form-control text-sm">
                                <option value="">{{ __('No Grouping') }}</option>
                                <option value="recipient" {{ request('group') === 'recipient' ? 'selected' : '' }}>{{ __('By Recipient') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-brand-500 rounded-md hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                                {{ __('Filter') }}
                            </button>
                            @if(request()->hasAny(['search', 'status', 'date_from', 'date_to', 'mailable_class', 'group']))
                                <a href="{{ route('admin.email-logs.index') }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                    {{ __('Reset') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Bulk Actions Bar --}}
                <div x-show="selectedIds.length > 0" x-cloak
                    class="px-5 py-2.5 sm:px-6 border-t border-gray-100 dark:border-gray-800 bg-blue-50 dark:bg-blue-900/20 flex items-center gap-3">
                    <span class="text-sm text-blue-700 dark:text-blue-300" x-text="selectedIds.length + ' selected'"></span>
                    <button @click="batchRetry()"
                        :disabled="batchLoading"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
                        <span x-show="!batchLoading">{{ __('Retry Selected') }}</span>
                        <span x-show="batchLoading">{{ __('Retrying...') }}</span>
                    </button>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800 overflow-x-auto">
                    @if($grouped)
                        {{-- Grouped by Recipient View --}}
                        <table class="w-full dark:text-gray-300">
                            <thead class="bg-light text-capitalize">
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">{{ __('Email') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-center">{{ __('Total') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-center">{{ __('Sent') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-center">{{ __('Failed') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-center">{{ __('Bounced') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">{{ __('Latest') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($grouped as $row)
                                    <tr class="{{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                        <td class="px-5 py-4 sm:px-6 text-left">
                                            <a href="{{ route('admin.email-logs.index', ['search' => $row->to]) }}"
                                                class="text-brand-500 hover:underline">{{ $row->to }}</a>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-center font-medium">{{ $row->total }}</td>
                                        <td class="px-5 py-4 sm:px-6 text-center text-green-600 dark:text-green-400">{{ $row->sent_count }}</td>
                                        <td class="px-5 py-4 sm:px-6 text-center text-red-600 dark:text-red-400">{{ $row->failed_count }}</td>
                                        <td class="px-5 py-4 sm:px-6 text-center text-orange-600 dark:text-orange-400">{{ $row->bounced_count }}</td>
                                        <td class="px-5 py-4 sm:px-6 text-left text-sm text-gray-500">
                                            {{ \Carbon\Carbon::parse($row->latest_at)->format('d M Y H:i A') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <p class="text-gray-500 dark:text-gray-300">{{ __('No email logs found') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="my-4 px-4 sm:px-6">
                            {{ $grouped->links() }}
                        </div>
                    @elseif($emailLogs)
                        {{-- Normal Table View --}}
                        @php
                            $currentSort = request('sort', 'created_at');
                            $currentDir = request('dir', 'desc');
                            $sortParams = request()->except(['sort', 'dir']);
                        @endphp
                        <table class="w-full dark:text-gray-300">
                            <thead class="bg-light text-capitalize">
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="bg-gray-50 dark:bg-gray-800 px-5 p-2 sm:px-6 text-left w-10">
                                        <input type="checkbox" @change="toggleSelectAll($event)" class="rounded border-gray-300 dark:border-gray-600">
                                    </th>
                                    @php
                                        $columns = [
                                            'to' => __('To'),
                                            'subject' => __('Subject'),
                                            'mailable_class' => __('Type'),
                                            'status' => __('Status'),
                                            'created_at' => __('Sent At'),
                                        ];
                                    @endphp
                                    @foreach($columns as $col => $label)
                                        <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                            <a href="{{ route('admin.email-logs.index', array_merge($sortParams, ['sort' => $col, 'dir' => ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc'])) }}"
                                                class="inline-flex items-center gap-1 hover:text-brand-500">
                                                {{ $label }}
                                                @if($currentSort === $col)
                                                    <svg class="w-3 h-3 {{ $currentDir === 'desc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                    </svg>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">{{ __('Error') }}</th>
                                    <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($emailLogs as $log)
                                    <tr class="{{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                        <td class="px-5 py-4 sm:px-6 text-left">
                                            <input type="checkbox" value="{{ $log->id }}" x-model="selectedIds" class="rounded border-gray-300 dark:border-gray-600">
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left text-sm">{{ $log->to }}</td>
                                        <td class="px-5 py-4 sm:px-6 text-left text-sm max-w-[200px]">
                                            <button @click="viewDetail({{ $log->id }})" class="text-left text-brand-500 hover:underline truncate block max-w-full" title="{{ $log->subject }}">
                                                {{ Str::limit($log->subject, 40) }}
                                            </button>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $log->mailable_class ? class_basename($log->mailable_class) : '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left">
                                            @switch($log->status)
                                                @case('sent')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Sent') }}</span>
                                                    @break
                                                @case('failed')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">{{ __('Failed') }}</span>
                                                    @break
                                                @case('bounced')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200">{{ __('Bounced') }}</span>
                                                    @break
                                                @case('pending')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Pending') }}</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left text-sm text-gray-500">
                                            {{ $log->sent_at ? $log->sent_at->format('d M Y H:i A') : ($log->created_at ? $log->created_at->format('d M Y H:i A') : '-') }}
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left text-sm max-w-[150px]">
                                            @if($log->error_message)
                                                <span class="text-red-500 dark:text-red-400 truncate block" title="{{ $log->error_message }}">{{ Str::limit($log->error_message, 30) }}</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 text-left">
                                            <div class="flex items-center gap-1.5">
                                                <button @click="viewDetail({{ $log->id }})" title="{{ __('View') }}"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-gray-500 hover:text-brand-500 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </button>
                                                @if(in_array($log->status, ['failed', 'bounced']) && $log->body_html && $log->retry_count < 3)
                                                    <button @click="retryOne({{ $log->id }})" title="{{ __('Retry') }}"
                                                        class="inline-flex items-center justify-center w-7 h-7 text-gray-500 hover:text-green-600 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <p class="text-gray-500 dark:text-gray-300">{{ __('No email logs found') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="my-4 px-4 sm:px-6">
                            {{ $emailLogs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Detail Drawer --}}
        <div x-show="drawerOpen" x-cloak
            class="fixed inset-0 z-50 flex justify-end"
            @keydown.escape.window="drawerOpen = false">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40" @click="drawerOpen = false"></div>
            {{-- Panel --}}
            <div class="relative w-full max-w-xl bg-white dark:bg-gray-900 shadow-xl overflow-y-auto"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full">

                <div x-show="drawerLoading" class="flex items-center justify-center py-20">
                    <svg class="animate-spin h-8 w-8 text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>

                <template x-if="detail && !drawerLoading">
                    <div>
                        {{-- Header --}}
                        <div class="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Email Detail') }}</h3>
                            <button @click="drawerOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Metadata --}}
                        <div class="px-6 py-4 space-y-3 border-b border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-[100px_1fr] gap-y-2 text-sm">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('To') }}</span>
                                <span class="text-gray-900 dark:text-white" x-text="detail.to"></span>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Subject') }}</span>
                                <span class="text-gray-900 dark:text-white" x-text="detail.subject"></span>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Type') }}</span>
                                <span class="text-gray-500" x-text="detail.mailable_short_name || '-'"></span>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Status') }}</span>
                                <span>
                                    <span x-show="detail.status === 'sent'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">{{ __('Sent') }}</span>
                                    <span x-show="detail.status === 'failed'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">{{ __('Failed') }}</span>
                                    <span x-show="detail.status === 'bounced'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200">{{ __('Bounced') }}</span>
                                    <span x-show="detail.status === 'pending'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Pending') }}</span>
                                </span>

                                <template x-if="detail.error_message">
                                    <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Error') }}</span>
                                </template>
                                <template x-if="detail.error_message">
                                    <span class="text-red-500 dark:text-red-400 text-xs break-all" x-text="detail.error_message"></span>
                                </template>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Retries') }}</span>
                                <span class="text-gray-500" x-text="detail.retry_count"></span>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Sent At') }}</span>
                                <span class="text-gray-500" x-text="detail.sent_at || '-'"></span>

                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __('Created') }}</span>
                                <span class="text-gray-500" x-text="detail.created_at"></span>
                            </div>
                        </div>

                        {{-- Body Preview --}}
                        <div class="px-6 py-4">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Email Body') }}</h4>
                            <template x-if="detail.body_html">
                                <iframe :srcdoc="detail.body_html" sandbox="allow-same-origin" class="w-full border border-gray-200 dark:border-gray-700 rounded-md bg-white" style="min-height: 400px;"></iframe>
                            </template>
                            <template x-if="!detail.body_html">
                                <p class="text-sm text-gray-400 italic">{{ __('Email body not available (logged before body capture was enabled).') }}</p>
                            </template>
                        </div>

                        {{-- Retry Button --}}
                        <template x-if="detail.is_retryable">
                            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                                <button @click="retryFromDrawer()"
                                    :disabled="retryLoading"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">
                                    <span x-show="!retryLoading">{{ __('Retry This Email') }}</span>
                                    <span x-show="retryLoading">{{ __('Sending...') }}</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function emailLogPage() {
            return {
                selectedIds: [],
                drawerOpen: false,
                drawerLoading: false,
                detail: null,
                retryLoading: false,
                batchLoading: false,

                toggleSelectAll(event) {
                    if (event.target.checked) {
                        this.selectedIds = Array.from(document.querySelectorAll('tbody input[type=checkbox]')).map(cb => cb.value);
                    } else {
                        this.selectedIds = [];
                    }
                },

                async viewDetail(id) {
                    this.drawerOpen = true;
                    this.drawerLoading = true;
                    this.detail = null;
                    try {
                        const res = await fetch(`{{ url('admin/email-logs') }}/${id}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        this.detail = await res.json();
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.drawerLoading = false;
                    }
                },

                async retryOne(id) {
                    if (!confirm('{{ __("Resend this email?") }}')) return;
                    try {
                        const res = await fetch(`{{ url('admin/email-logs') }}/${id}/retry`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) location.reload();
                    } catch (e) {
                        alert('{{ __("Retry failed. Please try again.") }}');
                    }
                },

                async retryFromDrawer() {
                    if (!this.detail) return;
                    this.retryLoading = true;
                    try {
                        const res = await fetch(`{{ url('admin/email-logs') }}/${this.detail.id}/retry`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) location.reload();
                    } catch (e) {
                        alert('{{ __("Retry failed. Please try again.") }}');
                    } finally {
                        this.retryLoading = false;
                    }
                },

                async batchRetry() {
                    if (!confirm(`{{ __("Retry") }} ${this.selectedIds.length} {{ __("email(s)?") }}`)) return;
                    this.batchLoading = true;
                    try {
                        const res = await fetch(`{{ url('admin/email-logs/batch-retry') }}`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ ids: this.selectedIds.map(Number) })
                        });
                        const data = await res.json();
                        alert(data.message);
                        location.reload();
                    } catch (e) {
                        alert('{{ __("Batch retry failed. Please try again.") }}');
                    } finally {
                        this.batchLoading = false;
                    }
                }
            };
        }
    </script>
@endsection
