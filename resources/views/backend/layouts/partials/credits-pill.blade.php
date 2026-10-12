{{--
    The organization's credit balance, top right on every admin page. Fetched
    (never rendered server-side) so it stays cheap, and re-read whenever a
    page fires `credits:changed` after spending — e.g. Generate & Save.
--}}
@canany(['credits.view', 'credits.manage'])
@php
    // For whoever runs credits without an organization of their own: which
    // organization's balance this page is about (see CreditController::balance).
    $pillContext = [];
    if (request()->routeIs('admin.credits.index') && isset($org) && $org instanceof \App\Models\Organization) {
        $pillContext['org'] = $org->id;
    } elseif ($pillTournament = request()->route('tournament')) {
        $pillContext['tournament'] = $pillTournament instanceof \App\Models\Tournament ? $pillTournament->id : $pillTournament;
    }
@endphp
<div x-data="{
        c: null,
        async load() {
            try {
                const r = await fetch('{{ route('admin.credits.balance', $pillContext) }}', { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (r.ok) { this.c = await r.json(); window.creditBalance = this.c; window.dispatchEvent(new CustomEvent('credits:loaded', { detail: this.c })); }
            } catch (e) {}
        }
     }"
     x-init="load(); window.addEventListener('credits:changed', () => load())"
     class="mr-1">
    <template x-if="c && c.show">
        <a :href="c.url" class="flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
           :title="c.charges ? 'Your balance. Paid actions show their cost before you use them.' : 'Your balance (nothing is charged for your account).'">
            <iconify-icon icon="lucide:coins" width="15" height="15"></iconify-icon>
            <span x-text="c.label"></span>
        </a>
    </template>
    <template x-if="c && !c.show && c.manage_url">
        <a :href="c.manage_url" class="flex items-center gap-1.5 rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
            <iconify-icon icon="lucide:coins" width="15" height="15"></iconify-icon>
            <span>{{ app(\App\Services\Credits\CreditService::class)->term() }}</span>
        </a>
    </template>
</div>
@endcanany
