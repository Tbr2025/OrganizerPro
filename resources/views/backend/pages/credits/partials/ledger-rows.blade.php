{{-- Ledger rows for the credits admin page: when, who, what, and the signed amount. --}}
@php
    $labels = [
        'purchase' => ['Top-up', 'bg-emerald-50 text-emerald-700'], 'usage' => ['Spent', 'bg-rose-50 text-rose-700'],
        'refund' => ['Refund', 'bg-sky-50 text-sky-700'], 'adjustment' => ['Adjustment', 'bg-gray-100 text-gray-700'],
        'promo' => ['Promotional', 'bg-amber-50 text-amber-700'], 'reversal' => ['Reversal', 'bg-sky-50 text-sky-700'],
    ];
@endphp
<div class="overflow-x-auto">
<table class="w-full text-sm">
    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
    @forelse($rows as $e)
        @php [$label, $tone] = $labels[$e->type] ?? [ucfirst($e->type), 'bg-gray-100 text-gray-700']; @endphp
        <tr>
            <td class="py-2.5 pr-3 text-gray-500 whitespace-nowrap">{{ $e->created_at?->format('d M, H:i') }}</td>
            <td class="py-2.5 pr-3 font-medium text-gray-900 dark:text-white">{{ $e->wallet?->organization?->name }}</td>
            <td class="py-2.5 pr-3"><span class="px-2 py-0.5 rounded-full text-xs {{ $tone }}">{{ __($label) }}</span></td>
            <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-300">{{ $e->description ?: $e->feature_key }}{{ $e->creator ? ' · ' . $e->creator->name : '' }}</td>
            <td class="py-2.5 pr-3 text-right font-semibold whitespace-nowrap {{ $e->amount < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $e->amount > 0 ? '+' : '' }}{{ number_format($e->amount) }}</td>
            <td class="py-2.5 text-right text-gray-400 whitespace-nowrap text-xs">{{ __('balance') }} {{ number_format((int) $e->balance_after) }}</td>
        </tr>
    @empty
        <tr><td class="py-6 text-center text-gray-500">{{ __('No activity yet.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
