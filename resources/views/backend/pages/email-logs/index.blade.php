@extends('backend.layouts.app')

@section('title')
{{ __('Email Logs - ' . config('app.name')) }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="px-5 py-4 sm:px-6 sm:py-5 flex flex-col md:flex-row justify-between items-center gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Email Logs') }}</h3>
                    @if($emailLogs->total() > 0)
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

                {{-- Filter Bar --}}
                <div class="px-5 py-3 sm:px-6 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                    <form action="{{ route('admin.email-logs.index') }}" method="GET"
                        class="flex flex-col sm:flex-row flex-wrap items-end gap-3">
                        <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[180px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Search by Email') }}</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="{{ __('Recipient email...') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[130px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Status') }}</label>
                            <select name="status" class="form-control text-sm">
                                <option value="">{{ __('All') }}</option>
                                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ __('Sent') }}</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[150px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Date From') }}</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="w-full sm:w-auto sm:min-w-[150px]">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Date To') }}</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                class="form-control text-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-brand-500 rounded-md hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                                {{ __('Filter') }}
                            </button>
                            @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                                <a href="{{ route('admin.email-logs.index') }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                    {{ __('Reset') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 overflow-x-auto">
                    <table class="w-full dark:text-gray-300">
                        <thead class="bg-light text-capitalize">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('Sl') }}</th>
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('To') }}</th>
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('Subject') }}</th>
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('Type') }}</th>
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('Status') }}</th>
                                <th class="bg-gray-50 dark:bg-gray-800 dark:text-white px-5 p-2 sm:px-6 text-left">
                                    {{ __('Sent At') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($emailLogs as $log)
                                <tr class="{{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                    <td class="px-5 py-4 sm:px-6 text-left">{{ $loop->iteration + ($emailLogs->currentPage() - 1) * $emailLogs->perPage() }}</td>
                                    <td class="px-5 py-4 sm:px-6 text-left">{{ $log->to }}</td>
                                    <td class="px-5 py-4 sm:px-6 text-left">{{ Str::limit($log->subject, 50) }}</td>
                                    <td class="px-5 py-4 sm:px-6 text-left">
                                        <span class="text-xs text-gray-500">{{ $log->mailable_class ? class_basename($log->mailable_class) : '-' }}</span>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-left">
                                        @if ($log->status === 'sent')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                {{ __('Sent') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200" title="{{ $log->error_message }}">
                                                {{ __('Failed') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 sm:px-6 text-left">
                                        {{ $log->sent_at ? $log->sent_at->format('d M Y H:i A') : '-' }}
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
                        {{ $emailLogs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
