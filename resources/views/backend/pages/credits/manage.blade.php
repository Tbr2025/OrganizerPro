@extends('backend.layouts.app')

@section('title')
    {{ $credits->term() }} — {{ __('Manage') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
{{--
    Platform controls for credits, one tab per job. Every form posts to the
    same routes as before and comes back to the tab it was sent from (the tab
    lives in the URL, so a redirect back lands on it).
--}}
@php
    $term = $credits->term();
    $one = $credits->term(1);
    $card = 'rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900';
    $money = fn ($credits) => $rate > 0 ? trim($currency . ' ' . number_format($credits * $rate, 2)) : null;
    $typeLabels = [
        'purchase' => __('Top-up'), 'usage' => __('Spent'), 'refund' => __('Refund'),
        'adjustment' => __('Adjustment'), 'promo' => __('Promotional'), 'reversal' => __('Reversal'),
    ];
    $tabs = [
        'overview' => [__('Overview'), 'lucide:layout-dashboard', null],
        'organizations' => [__('Organizations'), 'lucide:building-2', null],
        'topups' => [__('Top-ups'), 'lucide:wallet', $kpis['pending'] ?: null],
        'prices' => [__('Prices'), 'lucide:tags', null],
        'discounts' => [__('Discounts'), 'lucide:percent', null],
        'settings' => [__('Settings'), 'lucide:settings-2', null],
        'activity' => [__('Activity'), 'lucide:history', null],
    ];
    $maxDay = max(1, $days->max());
    $maxFeature = max(1, (int) $usageByFeature->max('credits'));
@endphp

<div class="p-4 mx-auto sm:p-6 max-w-7xl space-y-6"
     x-data="{
        tab: @js($tab),
        go(t) { this.tab = t; const u = new URL(location); u.searchParams.set('tab', t); u.searchParams.delete('page'); history.replaceState(null, '', u); },
        adjust: { open: false, org: '', name: '', amount: '', type: 'promo', reason: '' },
        openAdjust(id, name) { this.adjust = { open: true, org: String(id ?? ''), name: name ?? '', amount: '', type: 'promo', reason: '' }; this.$nextTick(() => this.$refs.amount?.focus()); },
     }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Heading --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $term }} {{ __('control centre') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Every organization pays in :term for what it uses.', ['term' => $term]) }}
                @if($rate > 0) <span class="whitespace-nowrap">· 1 {{ $one }} = {{ $money(1) }}</span> @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="openAdjust(null, null)" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                <iconify-icon icon="lucide:plus-circle" width="16"></iconify-icon>{{ __('Add or remove :term', ['term' => $term]) }}
            </button>
            @if($kpis['pending'])
                <button type="button" @click="go('topups')" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">
                    <iconify-icon icon="lucide:bell-ring" width="16"></iconify-icon>{{ trans_choice(':n top-up waiting|:n top-ups waiting', $kpis['pending'], ['n' => $kpis['pending']]) }}
                </button>
            @endif
        </div>
    </div>

    {{-- Headline numbers --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach([
            [__(':term held by organizations', ['term' => $term]), number_format($kpis['held']), $money($kpis['held']) ? __('worth :m', ['m' => $money($kpis['held'])]) : null, 'lucide:coins', 'text-amber-600 bg-amber-50 dark:bg-amber-900/20'],
            [__('Spent in the last 30 days'), number_format($kpis['used30']), $money($kpis['used30']) ? __('worth :m', ['m' => $money($kpis['used30'])]) : null, 'lucide:trending-down', 'text-rose-600 bg-rose-50 dark:bg-rose-900/20'],
            [__('Top-ups waiting'), number_format($kpis['pending']), $kpis['pending'] ? trim($currency . ' ' . number_format($kpis['pendingValue'], 2)) . ' ' . __('to confirm') : __('Nothing to confirm'), 'lucide:hourglass', 'text-orange-600 bg-orange-50 dark:bg-orange-900/20'],
            [__('Organizations'), number_format($kpis['orgs']), __('each with its own wallet'), 'lucide:building-2', 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'],
            [__('Payments received'), trim($currency . ' ' . number_format($kpis['received'], 2)), __('from approved top-ups'), 'lucide:banknote', 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20'],
        ] as [$label, $value, $hint, $icon, $tone])
            <div class="{{ $card }} p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center {{ $tone }}"><iconify-icon icon="{{ $icon }}" width="16"></iconify-icon></span>
                </div>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</p>
                @if($hint)<p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- Health check --}}
    @if($mismatches)
        <div class="rounded-2xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 text-sm">
            <p class="font-semibold text-red-700 dark:text-red-300 flex items-center gap-2"><iconify-icon icon="lucide:alert-triangle" width="16"></iconify-icon>{{ __('Some wallets do not equal the sum of their history. Investigate before adjusting.') }}</p>
            <ul class="mt-2 ml-6 list-disc text-red-700 dark:text-red-300">@foreach($mismatches as $m)<li>{{ $m['organization'] }}: {{ __('balance') }} {{ $m['balance'] }}, {{ __('history adds up to') }} {{ $m['ledger_sum'] }}</li>@endforeach</ul>
        </div>
    @else
        <p class="text-xs text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5"><iconify-icon icon="lucide:shield-check" width="14"></iconify-icon>{{ __('All wallets match their history.') }}</p>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
        <nav class="flex gap-1 min-w-max">
            @foreach($tabs as $key => [$label, $icon, $badge])
                <button type="button" @click="go('{{ $key }}')"
                        :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm font-medium whitespace-nowrap">
                    <iconify-icon icon="{{ $icon }}" width="16"></iconify-icon>{{ $label }}
                    @if($badge)<span class="ml-1 px-1.5 rounded-full bg-amber-500 text-white text-[11px] font-bold">{{ $badge }}</span>@endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ============ Overview ============ --}}
    <section x-show="tab === 'overview'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 {{ $card }} p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __(':term spent per day', ['term' => $term]) }}</h3>
                <span class="text-xs text-gray-500">{{ __('last 14 days') }}</span>
            </div>
            <div class="flex items-end gap-1.5 h-40">
                @foreach($days as $d => $n)
                    <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end" title="{{ \Carbon\Carbon::parse($d)->format('d M') }}: {{ number_format($n) }}">
                        <span class="text-[10px] text-gray-500 {{ $n ? '' : 'invisible' }}">{{ $n }}</span>
                        <div class="w-full rounded-t-md {{ $n ? 'bg-indigo-500' : 'bg-gray-100 dark:bg-gray-800' }}" style="height: {{ $n ? max(4, round($n / $maxDay * 100)) : 4 }}%"></div>
                        <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($d)->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="{{ $card }} p-5">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('What it is spent on') }}</h3>
            <ul class="space-y-3 text-sm">
                @forelse($usageByFeature as $u)
                    <li>
                        <div class="flex justify-between"><span class="text-gray-700 dark:text-gray-200">{{ $features->firstWhere('key', $u->feature_key)?->name ?? $u->feature_key }} <span class="text-gray-400">× {{ $u->times }}</span></span><strong>{{ number_format((int) $u->credits) }}</strong></div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ round($u->credits / $maxFeature * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('Nothing charged yet. Prices start at 0 — set them on the Prices tab.') }}</li>
                @endforelse
            </ul>
            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800 grid grid-cols-2 gap-3 text-xs">
                @foreach(['purchased' => __('Bought'), 'granted' => __('Given free'), 'used' => __('Spent'), 'refunded' => __('Refunded')] as $k => $label)
                    <div><p class="text-gray-500">{{ $label }} ({{ __('all time') }})</p><p class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format((int) ($totals->$k ?? 0)) }}</p></div>
                @endforeach
            </div>
        </div>
        <div class="lg:col-span-3 {{ $card }} p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Latest activity') }}</h3>
                <button type="button" @click="go('activity')" class="text-sm text-indigo-600 hover:underline">{{ __('See all') }}</button>
            </div>
            @include('backend.pages.credits.partials.ledger-rows', ['rows' => $recent])
        </div>
    </section>

    {{-- ============ Organizations ============ --}}
    <section x-show="tab === 'organizations'" x-cloak class="{{ $card }}">
        <form method="GET" class="flex flex-wrap items-center gap-2 p-4 border-b border-gray-100 dark:border-gray-800">
            <input type="hidden" name="tab" value="organizations">
            <div class="relative flex-1 min-w-[200px]">
                <iconify-icon icon="lucide:search" width="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></iconify-icon>
                <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search organizations') }}" class="form-control pl-9 w-full">
            </div>
            <button class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">{{ __('Search') }}</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Organization') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Spent, 30 days') }}</th>
                        <th class="px-4 py-3">{{ __('Last activity') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($organizations as $o)
                    @php $stat = $perOrg[$o->id] ?? null; @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $o->name }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ ($balances[$o->id] ?? 0) <= 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">{{ number_format((int) ($balances[$o->id] ?? 0)) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format((int) ($stat->used30 ?? 0)) }}</td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $stat?->last_at ? \Carbon\Carbon::parse($stat->last_at)->diffForHumans() : '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" @click="openAdjust({{ $o->id }}, @js($o->name))" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 text-xs font-semibold">{{ __('Adjust balance') }}</button>
                            <a href="{{ route('admin.credits.index', ['org' => $o->id]) }}" class="ml-1 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs">{{ __('Wallet') }}</a>
                            <a href="{{ route('admin.credits.manage', ['tab' => 'activity', 'org' => $o->id]) }}" class="ml-1 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs">{{ __('History') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No organizations match.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ============ Top-ups ============ --}}
    <section x-show="tab === 'topups'" x-cloak class="space-y-6">
        <div class="{{ $card }}">
            <div class="p-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Waiting for you') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Check the payment arrived (UPI or bank reference), then approve. The :term are added at once.', ['term' => $term]) }}</p>
            </div>
            @forelse($pending as $t)
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-start justify-between gap-4">
                    <div class="text-sm">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $t->organization->name }}</p>
                        <p class="mt-0.5"><span class="text-lg font-bold">{{ number_format($t->credits) }}</span> {{ $credits->term($t->credits) }}
                            <span class="text-gray-500">{{ __('for') }}</span> <strong>{{ $t->currency }} {{ number_format((float) $t->price, 2) }}</strong>
                            @if($t->discount)<span class="ml-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">{{ $t->discount->name }}</span>@endif
                        </p>
                        <p class="text-xs text-gray-500 mt-1">{{ $t->requester?->name }} · {{ $t->created_at->format('d M Y, H:i') }} · {{ __('Payment ref') }}: <strong class="text-gray-700 dark:text-gray-200">{{ $t->payment_reference ?: '—' }}</strong></p>
                        @if($t->note)<p class="text-xs text-gray-600 dark:text-gray-300 mt-1">“{{ $t->note }}”</p>@endif
                    </div>
                    <form method="POST" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input name="admin_note" maxlength="255" placeholder="{{ __('Note to them (optional)') }}" class="form-control text-sm w-56">
                        <button formaction="{{ route('admin.credits.topups.approve', $t) }}" onclick="return confirm(@js(__('Payment received? Add :n :term to :org.', ['n' => number_format($t->credits), 'term' => $credits->term($t->credits), 'org' => $t->organization->name])))"
                                class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">{{ __('Approve') }}</button>
                        <button formaction="{{ route('admin.credits.topups.reject', $t) }}" onclick="return confirm(@js(__('Reject this request?')))"
                                class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">{{ __('Reject') }}</button>
                    </form>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-gray-500"><iconify-icon icon="lucide:check-circle-2" width="28" class="text-emerald-500"></iconify-icon><p class="mt-2">{{ __('Nothing waiting.') }}</p></div>
            @endforelse
        </div>
        <div class="{{ $card }}">
            <h3 class="p-4 border-b border-gray-100 dark:border-gray-800 font-semibold text-gray-900 dark:text-white">{{ __('Recently handled') }}</h3>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($processed as $t)
                    <tr>
                        <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ $t->processed_at?->format('d M Y') }}</td>
                        <td class="px-4 py-2.5">{{ $t->organization?->name }}</td>
                        <td class="px-4 py-2.5">{{ number_format($t->credits) }} · {{ $t->currency }} {{ number_format((float) $t->price, 2) }}</td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs {{ $t->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($t->status) }}</span></td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $t->admin_note }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-500">{{ __('No handled requests yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </section>

    {{-- ============ Prices ============ --}}
    <section x-show="tab === 'prices'" x-cloak class="space-y-4">
        <p class="text-sm text-gray-500">{{ __('What each feature costs. 0, or switched off, means free. Only organizations billed in :term pay, and they always see the price before anything is charged.', ['term' => $term]) }}</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($features as $f)
                <form method="POST" action="{{ route('admin.credits.features.update', $f) }}" class="{{ $card }} p-5 space-y-3"
                      x-data="{ cost: {{ (int) $f->cost }}, on: {{ $f->is_active ? 'true' : 'false' }} }">
                    @csrf
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $f->name }}</p>
                            <p class="text-xs text-gray-500">{{ $f->description ?: ($f->charge_trigger === 'unlock_once' ? __('Charged once per tournament') : __('Charged for each one')) }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs">
                            <input type="checkbox" name="is_active" value="1" x-model="on" class="sr-only">
                            <span class="relative inline-flex h-5 w-9 rounded-full transition" :class="on ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'">
                                <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition" :class="on ? 'left-[18px]' : 'left-0.5'"></span>
                            </span>
                            <span x-text="on ? @js(__('Charged')) : @js(__('Free'))"></span>
                        </label>
                    </div>
                    <div class="flex items-end gap-3">
                        <label class="flex-1 text-xs text-gray-600 dark:text-gray-300">{{ __('Price in :term', ['term' => $term]) }}
                            <input type="number" name="cost" min="0" x-model.number="cost" class="form-control mt-1 w-full">
                        </label>
                        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">{{ __('Save') }}</button>
                    </div>
                    @if($rate > 0)
                        <p class="text-xs text-gray-500" x-show="on && cost > 0">≈ {{ $currency }} <span x-text="(cost * {{ $rate }}).toFixed(2)"></span> {{ __('at today\'s rate') }}</p>
                    @endif
                    <p class="text-xs text-emerald-600" x-show="!on || cost == 0">{{ __('Free for everyone') }}</p>
                </form>
            @endforeach
        </div>
    </section>

    {{-- ============ Discounts ============ --}}
    <section x-show="tab === 'discounts'" x-cloak class="space-y-6">
        @can('discount.manage')
        <details class="{{ $card }} group" @if($errors->hasAny(['name', 'code', 'value', 'applies_to'])) open @endif>
            <summary class="cursor-pointer p-4 flex items-center justify-between font-semibold text-gray-900 dark:text-white">
                <span class="flex items-center gap-2"><iconify-icon icon="lucide:plus" width="16"></iconify-icon>{{ __('New discount') }}</span>
                <iconify-icon icon="lucide:chevron-down" width="16" class="group-open:rotate-180 transition"></iconify-icon>
            </summary>
            <form method="POST" action="{{ route('admin.credits.discounts.store') }}" class="p-4 pt-0 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                @csrf
                <label class="flex flex-col gap-1">{{ __('Name') }}<input name="name" required value="{{ old('name') }}" placeholder="{{ __('e.g. Launch offer') }}" class="form-control"></label>
                <label class="flex flex-col gap-1">{{ __('Code') }}<input name="code" value="{{ old('code') }}" placeholder="{{ __('Leave blank to apply automatically') }}" class="form-control"></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1">{{ __('Type') }}<select name="type" class="form-control"><option value="percent">{{ __('Percent off') }}</option><option value="fixed">{{ __(':term off', ['term' => $term]) }}</option></select></label>
                    <label class="flex flex-col gap-1">{{ __('Value') }}<input name="value" type="number" min="1" required value="{{ old('value') }}" class="form-control"></label>
                </div>
                <label class="flex flex-col gap-1">{{ __('Applies to') }}
                    <select name="applies_to" class="form-control">
                        <option value="features">{{ __('Every feature') }}</option>
                        <option value="topup">{{ __('Top-ups (buying :term)', ['term' => $term]) }}</option>
                        @foreach($features as $f)<option value="feature:{{ $f->key }}">{{ $f->name }}</option>@endforeach
                        <option value="feature:{{ \App\Services\Poster\TemplateLibrary::PREMIUM_FEATURE }}">{{ __('Premium template presets') }}</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1">{{ __('Starts') }}<input name="starts_at" type="date" class="form-control"></label>
                    <label class="flex flex-col gap-1">{{ __('Ends') }}<input name="ends_at" type="date" class="form-control"></label>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1">{{ __('Max uses') }}<input name="max_uses" type="number" min="1" placeholder="{{ __('No limit') }}" class="form-control"></label>
                    <label class="flex flex-col gap-1">{{ __('Per organization') }}<input name="per_org_limit" type="number" min="1" placeholder="{{ __('No limit') }}" class="form-control"></label>
                </div>
                <label class="md:col-span-2 flex flex-col gap-1">{{ __('Only for these organizations') }} <span class="text-xs text-gray-500">{{ __('None selected = everyone. Hold Ctrl / Cmd to pick several.') }}</span>
                    <select name="organization_ids[]" multiple size="4" class="form-control">
                        @foreach($allOrganizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                    </select>
                </label>
                @if($errors->hasAny(['name', 'code', 'value', 'applies_to']))<p class="md:col-span-3 text-red-600 text-xs">{{ $errors->first() }}</p>@endif
                <div class="md:col-span-3"><button class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Create discount') }}</button></div>
            </form>
        </details>
        @endcan
        <div class="{{ $card }} overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">{{ __('Discount') }}</th><th class="px-4 py-3">{{ __('Code') }}</th><th class="px-4 py-3">{{ __('Off') }}</th><th class="px-4 py-3">{{ __('On') }}</th><th class="px-4 py-3">{{ __('Valid') }}</th><th class="px-4 py-3">{{ __('Used') }}</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($discounts as $d)
                    @php
                        $expired = $d->ends_at && $d->ends_at->isPast();
                        $state = ! $d->is_active ? [__('Off'), 'bg-gray-100 text-gray-600'] : ($expired ? [__('Ended'), 'bg-gray-100 text-gray-600'] : [__('Live'), 'bg-emerald-50 text-emerald-700']);
                        $on = $d->applies_to === 'topup' ? __('Top-ups') : ($d->applies_to === 'features' ? __('Every feature') : ($d->applies_to === 'feature:' . \App\Services\Poster\TemplateLibrary::PREMIUM_FEATURE ? __('Premium template presets') : ($features->firstWhere('key', \Illuminate\Support\Str::after($d->applies_to, 'feature:'))?->name ?? $d->applies_to)));
                    @endphp
                    <tr>
                        <td class="px-4 py-3"><span class="font-medium text-gray-900 dark:text-white">{{ $d->name }}</span> <span class="ml-1 px-2 py-0.5 rounded-full text-xs {{ $state[1] }}">{{ $state[0] }}</span></td>
                        <td class="px-4 py-3">@if($d->code)<code class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800">{{ $d->code }}</code>@else<span class="text-gray-500">{{ __('automatic') }}</span>@endif</td>
                        <td class="px-4 py-3 font-semibold">{{ $d->type === 'percent' ? $d->value . '%' : number_format($d->value) }}</td>
                        <td class="px-4 py-3">{{ $on }}</td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $d->starts_at?->format('d M Y') ?? __('now') }} → {{ $d->ends_at?->format('d M Y') ?? __('no end') }}</td>
                        <td class="px-4 py-3">{{ $d->redemptions_count }}{{ $d->max_uses ? ' / ' . $d->max_uses : '' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('discount.manage')
                            <form method="POST" action="{{ route('admin.credits.discounts.toggle', $d) }}">@csrf<button class="text-xs font-semibold text-indigo-600 hover:underline">{{ $d->is_active ? __('Switch off') : __('Switch on') }}</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No discounts yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ============ Settings ============ --}}
    <section x-show="tab === 'settings'" x-cloak>
        <form method="POST" action="{{ route('admin.credits.settings.update') }}" class="{{ $card }} p-5 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm"
              x-data="{ rate: {{ (float) ($settings['money_per_credit'] ?: 1) }}, cur: @js((string) $settings['currency']), one: @js((string) $settings['term_singular']), many: @js((string) $settings['term_plural']) }">
            @csrf
            <div class="md:col-span-2">
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Price and wording') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Changes apply to new top-ups. Past top-ups keep the rate they were bought at.') }}</p>
            </div>
            <label class="flex flex-col gap-1">{{ __('Money per credit') }}<input name="money_per_credit" type="number" step="0.01" min="0.01" x-model.number="rate" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Currency') }}<input name="currency" x-model="cur" maxlength="8" class="form-control" placeholder="INR"></label>
            <label class="flex flex-col gap-1">{{ __('Word for one') }}<input name="term_singular" x-model="one" maxlength="30" class="form-control"></label>
            <label class="flex flex-col gap-1">{{ __('Word for many') }}<input name="term_plural" x-model="many" maxlength="30" class="form-control"></label>
            <div class="md:col-span-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 p-4 text-indigo-900 dark:text-indigo-100">
                <p class="text-xs uppercase tracking-wide text-indigo-500 mb-1">{{ __('Preview') }}</p>
                <p>1 <span x-text="one"></span> = <span x-text="cur"></span> <span x-text="(rate || 0).toFixed(2)"></span>
                   · 100 <span x-text="many"></span> = <span x-text="cur"></span> <span x-text="((rate || 0) * 100).toFixed(2)"></span>
                   · 1,000 <span x-text="many"></span> = <span x-text="cur"></span> <span x-text="((rate || 0) * 1000).toFixed(2)"></span></p>
            </div>
            <label class="flex flex-col gap-1">{{ __('Welcome :term for new organizations', ['term' => $term]) }}<input name="welcome_credits" type="number" min="0" value="{{ $settings['welcome_credits'] }}" class="form-control"><span class="text-xs text-gray-500">{{ __('Given once when an organization is created. 0 = none.') }}</span></label>
            <label class="md:col-span-2 flex flex-col gap-1">{{ __('Top-up instructions') }}<textarea name="topup_instructions" rows="3" class="form-control" placeholder="{{ __('e.g. Pay by UPI to sportzley@bank, then enter the reference number.') }}">{{ $settings['topup_instructions'] }}</textarea><span class="text-xs text-gray-500">{{ __('Shown to organizations when they request a top-up.') }}</span></label>
            @if($errors->hasAny(['money_per_credit', 'currency', 'term_singular', 'term_plural', 'welcome_credits', 'topup_instructions']))<p class="md:col-span-2 text-red-600 text-xs">{{ $errors->first() }}</p>@endif
            <div class="md:col-span-2"><button class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold">{{ __('Save settings') }}</button></div>
        </form>
    </section>

    {{-- ============ Activity ============ --}}
    <section x-show="tab === 'activity'" x-cloak class="{{ $card }}">
        <form method="GET" class="flex flex-wrap items-center gap-2 p-4 border-b border-gray-100 dark:border-gray-800">
            <input type="hidden" name="tab" value="activity">
            <select name="org" class="form-control w-auto" onchange="this.form.submit()">
                <option value="">{{ __('Every organization') }}</option>
                @foreach($allOrganizations as $o)<option value="{{ $o->id }}" @selected((int) request('org') === $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <select name="type" class="form-control w-auto" onchange="this.form.submit()">
                <option value="">{{ __('Every kind') }}</option>
                @foreach($typeLabels as $k => $label)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach
            </select>
            @if(request('org') || request('type'))<a href="{{ route('admin.credits.manage', ['tab' => 'activity']) }}" class="text-sm text-indigo-600 hover:underline">{{ __('Clear filters') }}</a>@endif
        </form>
        <div class="p-4">
            @include('backend.pages.credits.partials.ledger-rows', ['rows' => $activity])
        </div>
        <div class="px-4 pb-4">{{ $activity->links() }}</div>
    </section>

    {{-- ============ Adjust balance dialog ============ --}}
    <div x-show="adjust.open" x-cloak x-transition.opacity class="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="adjust.open = false" @click.self="adjust.open = false">
        <form method="POST" action="{{ route('admin.credits.grant') }}" class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 p-6 space-y-4 text-sm shadow-xl">
            @csrf
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Add or remove :term', ['term' => $term]) }}</h3>
                    <p class="text-xs text-gray-500" x-show="adjust.name" x-text="adjust.name"></p>
                </div>
                <button type="button" @click="adjust.open = false" class="text-gray-400 hover:text-gray-600"><iconify-icon icon="lucide:x" width="20"></iconify-icon></button>
            </div>
            <label class="flex flex-col gap-1" x-show="!adjust.name">{{ __('Organization') }}
                <select name="organization_id" x-model="adjust.org" required class="form-control">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach($allOrganizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </label>
            <div>
                <label class="flex flex-col gap-1">{{ __('Amount') }} <span class="text-xs text-gray-500">{{ __('Positive adds, negative removes.') }}</span>
                    <input type="number" name="amount" x-ref="amount" x-model.number="adjust.amount" required class="form-control text-lg font-semibold">
                </label>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach([50, 100, 500, 1000] as $n)
                        <button type="button" @click="adjust.amount = (Number(adjust.amount) || 0) + {{ $n }}" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold">+{{ $n }}</button>
                    @endforeach
                    @foreach([100, 500] as $n)
                        <button type="button" @click="adjust.amount = (Number(adjust.amount) || 0) - {{ $n }}" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">−{{ $n }}</button>
                    @endforeach
                </div>
                @if($rate > 0)<p class="text-xs text-gray-500 mt-1" x-show="adjust.amount">≈ {{ $currency }} <span x-text="Math.abs((adjust.amount || 0) * {{ $rate }}).toFixed(2)"></span></p>@endif
            </div>
            <label class="flex flex-col gap-1">{{ __('Kind') }}
                <select name="type" x-model="adjust.type" class="form-control">
                    <option value="promo">{{ __('Promotional (free :term)', ['term' => $term]) }}</option>
                    <option value="adjustment">{{ __('Adjustment (a correction)') }}</option>
                </select>
            </label>
            <label class="flex flex-col gap-1">{{ __('Reason') }} <span class="text-xs text-gray-500">{{ __('Shown in their history.') }}</span>
                <input name="reason" x-model="adjust.reason" required maxlength="255" class="form-control" placeholder="{{ __('e.g. Launch bonus') }}">
            </label>
            @error('amount')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="adjust.open = false" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600">{{ __('Cancel') }}</button>
                <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold"
                        :class="Number(adjust.amount) < 0 ? 'bg-rose-600' : 'bg-indigo-600'"
                        x-text="Number(adjust.amount) < 0 ? @js(__('Remove')) : @js(__('Add'))"></button>
            </div>
        </form>
    </div>
</div>
@endsection
