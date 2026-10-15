@extends('backend.layouts.app')

@section('title')
    {{ $credits->term() }} | {{ config('app.name') }}
@endsection

@section('admin-content')
@php $term = $credits->term(); @endphp
<div class="p-4 mx-auto sm:p-6 max-w-6xl space-y-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    @if($organizations->isNotEmpty())
        <form method="GET" class="flex items-center gap-2 text-sm">
            <label for="org" class="text-gray-600 dark:text-gray-300">{{ __('Organization') }}</label>
            <select id="org" name="org" class="form-control max-w-xs" onchange="this.form.submit()">
                @foreach($organizations as $o)
                    <option value="{{ $o->id }}" @selected($o->id === $org->id)>{{ $o->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    {{-- Balance --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-sm text-gray-500">{{ $org->name }} · {{ __('Balance') }}</p>
            <p class="text-4xl font-black text-gray-900 dark:text-white mt-1">{{ number_format($wallet->balance) }} <span class="text-lg font-semibold text-gray-500">{{ $credits->term($wallet->balance) }}</span></p>
        </div>

        {{-- Top-up request --}}
        <form method="POST" action="{{ route('admin.credits.topup') }}" class="md:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            @csrf
            @if($organizations->isNotEmpty())<input type="hidden" name="org" value="{{ $org->id }}">@endif
            <h3 class="sm:col-span-2 font-semibold text-gray-900 dark:text-white">{{ __('Request a top-up') }}</h3>
            <p class="sm:col-span-2 text-gray-500">{{ $credits->setting('topup_instructions') }}
                {{ __('Rate: :currency :rate per :one.', ['currency' => $credits->setting('currency'), 'rate' => $credits->setting('money_per_credit'), 'one' => $credits->term(1)]) }}</p>
            <label class="flex flex-col gap-1">{{ __('How many :term', ['term' => $term]) }}
                <input type="number" name="credits" min="1" required class="form-control" value="{{ old('credits') }}">
                @error('credits')<span class="text-red-600 text-xs">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1">{{ __('Discount code (optional)') }}
                <input name="discount_code" class="form-control" value="{{ old('discount_code') }}">
                @error('discount_code')<span class="text-red-600 text-xs">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1">{{ __('Payment reference (UPI / bank)') }}
                <input name="payment_reference" class="form-control" value="{{ old('payment_reference') }}">
            </label>
            <label class="flex flex-col gap-1">{{ __('Note') }}
                <input name="note" class="form-control" value="{{ old('note') }}">
            </label>
            <div class="sm:col-span-2"><button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">{{ __('Send request') }}</button></div>
        </form>
    </div>

    {{-- Prices --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ __('What costs :term', ['term' => $term]) }}</h3>
        @if($exempt)
            <p class="-mt-1 mb-3 text-xs text-gray-500">{{ __('These are the prices :org pays. You are never charged when you act on their tournaments as Super Admin.', ['org' => $org->name]) }}</p>
        @endif
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Feature') }}</th><th>{{ __('When') }}</th><th class="text-right">{{ __('Price') }}</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($features as $row)
                <tr>
                    <td class="py-2"><span class="font-medium text-gray-900 dark:text-white">{{ $row['feature']->name }}</span><br><span class="text-gray-500">{{ $row['feature']->description }}</span></td>
                    <td class="text-gray-500">{{ $row['feature']->charge_trigger === 'unlock_once' ? __('Once per tournament') : __('Each one') }}</td>
                    <td class="text-right font-semibold">
                        @if($row['quote']['free'])
                            <span class="text-green-600">{{ __('Free') }}</span>
                        @else
                            {{ $credits->format($row['quote']['cost']) }}
                            @if($row['quote']['saved'])<br><span class="text-xs text-green-600 font-normal">{{ __(':n off', ['n' => $row['quote']['saved']]) }}</span>@endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- History --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ __('History') }}</h3>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Date') }}</th><th>{{ __('What') }}</th><th class="text-right">{{ $term }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($ledger as $e)
                    <tr>
                        <td class="py-2 whitespace-nowrap text-gray-500">{{ $e->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $e->description ?: ucfirst($e->type) }} <span class="text-xs text-gray-400">#{{ $e->id }}</span></td>
                        <td class="text-right font-semibold {{ $e->amount < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $e->amount > 0 ? '+' : '' }}{{ number_format($e->amount) }}</td>
                        <td class="text-right">{{ number_format($e->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-gray-500">{{ __('No activity yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div class="mt-3">{{ $ledger->withQueryString()->links() }}</div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ __('Top-up requests') }}</h3>
                <ul class="space-y-2 text-sm">
                    @forelse($topups as $t)
                        <li class="flex justify-between gap-2">
                            <span>{{ number_format($t->credits) }} · {{ $t->currency }} {{ $t->price }}<br><span class="text-xs text-gray-500">{{ $t->created_at->format('d M Y') }}{{ $t->payment_reference ? ' · ' . $t->payment_reference : '' }}</span></span>
                            <span class="text-xs font-semibold {{ ['approved' => 'text-green-600', 'rejected' => 'text-red-600'][$t->status] ?? 'text-amber-600' }}">{{ ucfirst($t->status) }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('None yet.') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ __('Unlocked') }}</h3>
                <ul class="space-y-1 text-sm">
                    @forelse($unlocks as $u)
                        <li>{{ $features->firstWhere('feature.key', $u->feature_key)['feature']->name ?? $u->feature_key }} — {{ optional($u->scope)->name ?? '#' . $u->scope_id }}</li>
                    @empty
                        <li class="text-gray-500">{{ __('Nothing yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
