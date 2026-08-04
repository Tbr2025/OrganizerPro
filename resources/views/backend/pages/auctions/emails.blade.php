@extends('backend.layouts.app')

@section('title', 'Email Outbox · ' . $auction->name . ' | ' . config('app.name'))

@php
    $pending = (int) ($counts['pending'] ?? 0);
    $sent = (int) ($counts['sent'] ?? 0);
    $skipped = (int) ($counts['skipped'] ?? 0);
    $failed = (int) ($counts['failed'] ?? 0);

    $tabs = [
        null => ['label' => 'All', 'count' => $pending + $sent + $skipped + $failed],
        'pending' => ['label' => 'Waiting', 'count' => $pending],
        'sent' => ['label' => 'Sent', 'count' => $sent],
        'skipped' => ['label' => 'Not sent', 'count' => $skipped],
        'failed' => ['label' => 'Failed', 'count' => $failed],
    ];

    $badge = [
        'pending' => ['Waiting', 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
        'sent' => ['Sent', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
        'skipped' => ['Not sent', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
        'failed' => ['Failed', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
    ];
@endphp

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Email Outbox · {{ $auction->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Every player email this auction has raised — what went out, what is held, and what was
                suppressed. Settings live in
                <a href="{{ route('admin.auctions.edit', $auction) }}" class="underline">Edit config → Financial Settings</a>.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($failed > 0)
                <form action="{{ route('admin.auctions.emails.retry', $auction) }}" method="POST"
                      onsubmit="return confirm('Move {{ $failed }} failed email(s) back to the queue?')">
                    @csrf
                    <input type="hidden" name="scope" value="failed">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20">
                        Requeue {{ $failed }} failed
                    </button>
                </form>
            @endif
            @if($skipped > 0)
                <form action="{{ route('admin.auctions.emails.retry', $auction) }}" method="POST"
                      onsubmit="return confirm('Move {{ $skipped }} suppressed email(s) back to the queue?\n\nThese were held back by test mode. Turn test mode off before sending, or they will be skipped again.')">
                    @csrf
                    <input type="hidden" name="scope" value="skipped">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                        Requeue {{ $skipped }} not sent
                    </button>
                </form>
            @endif
            @if($pending > 0 && ! $auction->email_test_mode)
                <form action="{{ route('admin.auctions.emails.flush', $auction) }}" method="POST"
                      onsubmit="return confirm('Send {{ $pending }} held email(s) now?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                        Send now
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($auction->email_test_mode)
        <div class="mb-5 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 p-4">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Test mode is on</p>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                Messages are recorded here and marked <strong>Not sent</strong>. Nothing reaches a player
                until you turn test mode off in
                <a href="{{ route('admin.auctions.edit', $auction) }}" class="underline">Edit config</a>.
            </p>
        </div>
    @elseif(! ($auction->notifications_enabled ?? true))
        <div class="mb-5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Player emails are switched off</p>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                Nothing new is being recorded for this auction.
            </p>
        </div>
    @endif

    {{-- Status filter --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($tabs as $key => $tab)
            @php $isActive = $activeStatus === $key; @endphp
            <a href="{{ route('admin.auctions.emails.index', $auction) . ($key ? '?status=' . $key : '') }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border transition
                      {{ $isActive
                          ? 'border-blue-600 bg-blue-600 text-white'
                          : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                {{ $tab['label'] }}
                <span class="text-xs {{ $isActive ? 'text-blue-100' : 'text-gray-500 dark:text-gray-400' }}">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Listing --}}
    <div class="overflow-hidden bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-4 py-3 font-semibold">Player</th>
                        <th class="px-4 py-3 font-semibold">Email</th>
                        <th class="px-4 py-3 font-semibold">Message</th>
                        <th class="px-4 py-3 font-semibold">Team</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        @php [$label, $classes] = $badge[$email->status] ?? [ucfirst($email->status), 'bg-gray-100 text-gray-700']; @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $email->player?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $email->player?->user?->email ?? '— no address —' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $email->typeLabel() }}
                                @if(isset($email->payload['amount']))
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        · {{ $auction->formatAmount($email->payload['amount']) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $email->team?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $classes }}">{{ $label }}</span>
                                @if($email->error)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">{{ $email->error }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ ($email->sent_at ?? $email->created_at)?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                Nothing here yet. Player emails appear as players are sold or passed.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($emails->hasPages())
        <div class="mt-4">{{ $emails->links() }}</div>
    @endif
</div>
@endsection
