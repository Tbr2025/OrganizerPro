@extends('backend.layouts.app')

@section('title')
    {{ $credits->term() }} — {{ __('Manage') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
@php $term = $credits->term(); $card = 'rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5'; @endphp
<div class="p-4 mx-auto sm:p-6 max-w-7xl space-y-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Totals + reconciliation --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach(['purchased' => __('Purchased'), 'granted' => __('Granted'), 'used' => __('Used'), 'refunded' => __('Refunded')] as $k => $label)
            <div class="{{ $card }}"><p class="text-xs text-gray-500">{{ $label }}</p><p class="text-2xl font-bold">{{ number_format((int) ($totals->$k ?? 0)) }}</p></div>
        @endforeach
        <div class="{{ $card }} {{ $mismatches ? 'border-red-400' : '' }}">
            <p class="text-xs text-gray-500">{{ __('Wallets matching their ledger') }}</p>
            <p class="text-2xl font-bold {{ $mismatches ? 'text-red-600' : 'text-green-600' }}">{{ $mismatches ? count($mismatches) . ' ' . __('mismatch') : __('All') }}</p>
        </div>
    </div>
    @if($mismatches)
        <div class="{{ $card }} border-red-400 text-sm">
            <p class="font-semibold text-red-600 mb-2">{{ __('These wallets do not equal the sum of their history. Investigate; do not adjust blindly.') }}</p>
            <ul>@foreach($mismatches as $m)<li>{{ $m['organization'] }}: {{ __('balance') }} {{ $m['balance'] }}, {{ __('ledger') }} {{ $m['ledger_sum'] }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Pending top-ups --}}
    <div class="{{ $card }}">
        <h3 class="font-semibold mb-3">{{ __('Top-up requests waiting') }}</h3>
        @forelse($pending as $t)
            <div class="flex flex-wrap items-center justify-between gap-3 py-2 border-b border-gray-100 dark:border-gray-800 text-sm">
                <div>
                    <strong>{{ $t->organization->name }}</strong> — {{ number_format($t->credits) }} {{ $credits->term($t->credits) }} {{ __('for') }} {{ $t->currency }} {{ $t->price }}
                    @if($t->discount)<span class="text-green-600">({{ $t->discount->name }})</span>@endif
                    <br><span class="text-gray-500">{{ $t->requester?->name }} · {{ $t->created_at->format('d M Y H:i') }} · {{ __('Ref') }}: {{ $t->payment_reference ?: '—' }} {{ $t->note ? '· ' . $t->note : '' }}</span>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.credits.topups.approve', $t) }}" onsubmit="return confirm('{{ __('Payment received? Add the credits.') }}')">@csrf<button class="px-3 py-1.5 rounded-lg bg-green-600 text-white">{{ __('Approve') }}</button></form>
                    <form method="POST" action="{{ route('admin.credits.topups.reject', $t) }}">@csrf<button class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Reject') }}</button></form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ __('Nothing waiting.') }}</p>
        @endforelse
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Organizations --}}
        <div class="{{ $card }}">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">{{ __('Organizations') }}</h3>
                <form method="GET"><input name="q" value="{{ request('q') }}" placeholder="{{ __('Search') }}" class="form-control text-sm"></form>
            </div>
            <div class="overflow-x-auto max-h-96">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Name') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('Billing') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($organizations as $o)
                    <tr>
                        <td class="py-2"><a class="text-indigo-600" href="{{ route('admin.credits.index', ['org' => $o->id]) }}">{{ $o->name }}</a></td>
                        <td class="text-right">{{ number_format((int) ($balances[$o->id] ?? 0)) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.credits.organizations.mode', $o) }}">@csrf
                                <select name="billing_mode" class="form-control text-xs" onchange="if(confirm('{{ __('Change how this organization is billed?') }}')) this.form.submit()">
                                    <option value="package" @selected($o->billing_mode !== 'credits')>{{ __('Package limits') }}</option>
                                    <option value="credits" @selected($o->billing_mode === 'credits')>{{ $term }}</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>

        {{-- Grant --}}
        <form method="POST" action="{{ route('admin.credits.grant') }}" class="{{ $card }} space-y-3 text-sm">
            @csrf
            <h3 class="font-semibold">{{ __('Add or remove :term', ['term' => $term]) }}</h3>
            <select name="organization_id" required class="form-control">
                <option value="">{{ __('Organization…') }}</option>
                @foreach($organizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
            <div class="grid grid-cols-2 gap-3">
                <input type="number" name="amount" required placeholder="{{ __('Amount (negative removes)') }}" class="form-control">
                <select name="type" class="form-control"><option value="promo">{{ __('Promotional') }}</option><option value="adjustment">{{ __('Adjustment') }}</option></select>
            </div>
            <input name="reason" required maxlength="255" placeholder="{{ __('Reason (shown in their history)') }}" class="form-control">
            @error('amount')<span class="text-red-600 text-xs">{{ $message }}</span>@enderror
            <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Save') }}</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Feature prices --}}
        <div class="{{ $card }}">
            <h3 class="font-semibold mb-3">{{ __('Prices') }}</h3>
            @foreach($features as $f)
                <form method="POST" action="{{ route('admin.credits.features.update', $f) }}" class="flex flex-wrap items-center gap-3 py-2 border-b border-gray-100 dark:border-gray-800 text-sm">
                    @csrf
                    <div class="flex-1 min-w-[12rem]"><strong>{{ $f->name }}</strong><br><span class="text-gray-500">{{ $f->charge_trigger === 'unlock_once' ? __('Once per tournament') : __('Each one') }}</span></div>
                    <input type="number" name="cost" min="0" value="{{ $f->cost }}" class="form-control w-24">
                    <label class="flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($f->is_active)> {{ __('Charged') }}</label>
                    <button class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Save') }}</button>
                </form>
            @endforeach
            <p class="text-xs text-gray-500 mt-2">{{ __('0 or unticked = free. Only organizations billed in :term pay.', ['term' => $term]) }}</p>
        </div>

        {{-- Settings --}}
        <form method="POST" action="{{ route('admin.credits.settings.update') }}" class="{{ $card }} grid grid-cols-2 gap-3 text-sm">
            @csrf
            <h3 class="col-span-2 font-semibold">{{ __('Settings') }}</h3>
            <label class="flex flex-col gap-1">{{ __('Money per credit') }}<input name="money_per_credit" type="number" step="0.01" min="0.01" value="{{ $settings['money_per_credit'] }}" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Currency') }}<input name="currency" value="{{ $settings['currency'] }}" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Word for one') }}<input name="term_singular" value="{{ $settings['term_singular'] }}" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Word for many') }}<input name="term_plural" value="{{ $settings['term_plural'] }}" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Welcome credits for new organizations') }}<input name="welcome_credits" type="number" min="0" value="{{ $settings['welcome_credits'] }}" class="form-control"></label>
            <label class="col-span-2 flex flex-col gap-1">{{ __('Top-up instructions') }}<textarea name="topup_instructions" rows="2" class="form-control">{{ $settings['topup_instructions'] }}</textarea></label>
            @if($errors->any())<p class="col-span-2 text-red-600 text-xs">{{ $errors->first() }}</p>@endif
            <div class="col-span-2"><button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Save settings') }}</button></div>
        </form>
    </div>

    {{-- Discounts --}}
    @can('discount.manage')
    <div class="{{ $card }}">
        <h3 class="font-semibold mb-3">{{ __('Discounts') }}</h3>
        <form method="POST" action="{{ route('admin.credits.discounts.store') }}" class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm mb-4">
            @csrf
            <input name="name" required placeholder="{{ __('Name') }}" class="form-control md:col-span-2">
            <input name="code" placeholder="{{ __('Code (blank = automatic)') }}" class="form-control">
            <select name="type" class="form-control"><option value="percent">%</option><option value="fixed">{{ __('Fixed') }}</option></select>
            <input name="value" type="number" min="1" required placeholder="{{ __('Value') }}" class="form-control">
            <select name="applies_to" class="form-control">
                <option value="features">{{ __('All features') }}</option>
                <option value="topup">{{ __('Top-ups') }}</option>
                @foreach($features as $f)<option value="feature:{{ $f->key }}">{{ $f->name }}</option>@endforeach
            </select>
            <input name="starts_at" type="date" class="form-control" title="{{ __('Starts') }}">
            <input name="ends_at" type="date" class="form-control" title="{{ __('Ends') }}">
            <input name="max_uses" type="number" min="1" placeholder="{{ __('Max uses') }}" class="form-control">
            <input name="per_org_limit" type="number" min="1" placeholder="{{ __('Per organization') }}" class="form-control">
            <select name="organization_ids[]" multiple class="form-control md:col-span-2" title="{{ __('Only these organizations (none selected = everyone)') }}">
                @foreach($organizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Create') }}</button>
        </form>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-2">{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Value') }}</th><th>{{ __('On') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Used') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($discounts as $d)
                <tr class="{{ $d->is_active ? '' : 'opacity-50' }}">
                    <td class="py-2">{{ $d->name }}</td>
                    <td>{{ $d->code ?? __('automatic') }}</td>
                    <td>{{ $d->type === 'percent' ? $d->value . '%' : $d->value }}</td>
                    <td>{{ $d->applies_to }}</td>
                    <td class="text-gray-500">{{ $d->starts_at?->format('d M') ?? '—' }} → {{ $d->ends_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $d->redemptions_count }}{{ $d->max_uses ? ' / ' . $d->max_uses : '' }}</td>
                    <td><form method="POST" action="{{ route('admin.credits.discounts.toggle', $d) }}">@csrf<button class="text-xs text-indigo-600">{{ $d->is_active ? __('Switch off') : __('Switch on') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-3 text-gray-500">{{ __('No discounts yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endcan

    {{-- Usage + recent --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="{{ $card }}">
            <h3 class="font-semibold mb-3">{{ __('Usage by feature') }}</h3>
            <ul class="text-sm space-y-1">
                @forelse($usageByFeature as $u)
                    <li class="flex justify-between"><span>{{ $features->firstWhere('key', $u->feature_key)?->name ?? $u->feature_key }} × {{ $u->times }}</span><strong>{{ number_format((int) $u->credits) }}</strong></li>
                @empty
                    <li class="text-gray-500">{{ __('Nothing charged yet.') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="lg:col-span-2 {{ $card }}">
            <h3 class="font-semibold mb-3">{{ __('Recent activity') }}</h3>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($recent as $e)
                    <tr>
                        <td class="py-1.5 text-gray-500 whitespace-nowrap">{{ $e->created_at?->format('d M H:i') }}</td>
                        <td>{{ $e->wallet?->organization?->name }}</td>
                        <td>{{ $e->description ?: $e->type }}{{ $e->creator ? ' · ' . $e->creator->name : '' }}</td>
                        <td class="text-right font-semibold {{ $e->amount < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $e->amount > 0 ? '+' : '' }}{{ number_format($e->amount) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-3 text-gray-500">{{ __('No activity yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
@endsection
