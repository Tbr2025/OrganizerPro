<script setup>
import { computed, onMounted, ref } from 'vue';
import { get, post } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor } from '../lib/money';

const props = defineProps({
    boot: { type: Object, required: true },
});

const s = ref(props.boot.snapshot ?? {});
const queue = ref(props.boot.queue ?? []);
const can = props.boot.can ?? { sell: false, control: false };

const busy = ref('');
const error = ref('');
const notice = ref('');
const pushUp = ref(false);
const lastBidId = ref(0);

const cp = computed(() => s.value.current_player ?? null);
const teams = computed(() => s.value.teams ?? []);
const stats = computed(() => s.value.stats ?? {});
const paused = computed(() => s.value.auction_status === 'paused');
const sealedPending = computed(() => Boolean(s.value.sealed_threshold_pending));

const money = moneyFor(props.boot.amountUnit);

async function reconcile() {
    try {
        const data = await get(props.boot.urls.snapshot, 'panel');
        s.value = data;
        error.value = '';
    } catch (e) {
        if (e.name !== 'AbortError') error.value = 'Reconnecting…';
    }
}

function applyRaise(e) {
    const id = Number(e.bid_id ?? 0);
    if (id && id <= lastBidId.value) return;
    lastBidId.value = id;

    if (!s.value.current_player) return;

    s.value = {
        ...s.value,
        current_player: {
            ...s.value.current_player,
            current_price: e.current_price,
            leader: e.team_name ?? s.value.current_player.leader,
        },
    };
}

/**
 * Every write goes to the existing endpoint, unchanged.
 *
 * No new write path was added for this panel: sell, pass and next are the same POSTs the classic
 * one uses, with the same guards behind them. The server's own message is surfaced verbatim,
 * because "your team is over its ceiling" or "the auction is paused" is what the operator needs
 * to read — not a generic failure.
 */
async function act(key, url, body = {}, confirmText = null) {
    if (busy.value) return;
    if (confirmText && !window.confirm(confirmText)) return;

    busy.value = key;
    error.value = '';
    notice.value = '';

    try {
        const data = await post(url, body);
        notice.value = data.message ?? 'Done.';
        await reconcile();
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = '';
    }
}

const sell = () => act('sell', props.boot.urls.sell,
    { auction_player_id: cp.value?.id },
    `Sell ${cp.value?.name} to ${cp.value?.leader} for ${money(cp.value?.current_price)}?`);

const pass = () => act('pass', props.boot.urls.pass,
    { auction_player_id: cp.value?.id },
    `Pass ${cp.value?.name} with no sale?`);

const next = () => act('next', props.boot.urls.onBid, {});
const togglePause = () => act('pause', props.boot.urls.togglePause, {});

onMounted(() => {
    const rt = connect({
        auctionId: props.boot.auctionId,
        isSealedActive: () => sealedPending.value,
        reconcile,
        onFrame: (name, e) => {
            if (name === 'bid.raised') applyRaise(e);
        },
    });

    setInterval(() => { pushUp.value = rt.isConnected(); }, 1000);
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <header class="flex items-center gap-3 px-5 py-3 border-b border-slate-800">
            <div class="min-w-0">
                <p class="text-sm font-semibold truncate">{{ boot.auctionName }}</p>
                <p class="text-xs text-slate-400">
                    {{ s.active_pool?.name ?? 'No active pool' }}
                    · {{ stats.sold_count ?? 0 }} sold · {{ stats.waiting_count ?? 0 }} waiting
                </p>
            </div>

            <span v-if="paused" class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-full bg-amber-500/20 text-amber-300">
                Paused
            </span>
            <span class="ml-auto text-[10px] uppercase tracking-wide px-2 py-1 rounded-full"
                  :class="pushUp ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'">
                {{ pushUp ? 'Live' : 'Slow link' }}
            </span>
            <!-- Everything this panel does not cover lives on the classic one. -->
            <a :href="boot.urls.classic"
               class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-full bg-slate-800 text-slate-300">
                Full panel
            </a>
        </header>

        <p v-if="error" class="px-5 py-2 text-xs bg-rose-500/10 text-rose-300">{{ error }}</p>
        <p v-else-if="notice" class="px-5 py-2 text-xs bg-emerald-500/10 text-emerald-300">{{ notice }}</p>

        <!-- An observe-only auctioneer gets the panel without the controls, rather than buttons
             that would refuse them. -->
        <p v-if="!can.sell && !can.control"
           class="px-5 py-2 text-xs bg-slate-800/60 text-slate-400">
            Read-only — you can watch this auction but not call it.
        </p>

        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 p-5">
            <section class="lg:col-span-2 space-y-5">
                <div class="rounded-2xl bg-slate-900 p-5">
                    <template v-if="cp">
                        <div class="flex items-center gap-4">
                            <img v-if="cp.image_path" :src="`/storage/${cp.image_path}`" alt=""
                                 class="w-20 h-20 rounded-xl object-cover bg-slate-800">
                            <div class="min-w-0">
                                <p class="text-3xl font-black truncate">{{ cp.name }}</p>
                                <p class="text-sm text-slate-400">
                                    {{ cp.player_type ?? '' }}
                                    <span v-if="cp.lot_number"> · Lot {{ cp.lot_number }}</span>
                                    · base {{ money(cp.base_price) }}
                                </p>
                            </div>
                            <div class="ml-auto text-right">
                                <p class="text-[10px] uppercase tracking-wide text-slate-500">Current</p>
                                <p class="text-4xl font-black tabular-nums">{{ money(cp.current_price) }}</p>
                                <p class="text-sm text-slate-400">{{ cp.leader ?? 'No bids' }}</p>
                            </div>
                        </div>

                        <div v-if="s.timer_enabled" class="mt-4 text-xs text-slate-400">
                            Timer {{ s.timer_seconds_remaining ?? 0 }}s
                            <span v-if="s.timer_paused"> · paused</span>
                            <span v-if="s.timer_expired" class="text-rose-400"> · expired</span>
                        </div>

                        <div v-if="can.sell || can.control" class="mt-5 grid grid-cols-2 gap-3">
                            <button v-if="can.sell" type="button" :disabled="!!busy || !cp.leader" @click="sell"
                                    class="py-4 rounded-xl bg-emerald-500 text-slate-950 font-black disabled:opacity-30">
                                {{ busy === 'sell' ? 'Selling…' : 'SELL' }}
                            </button>
                            <button v-if="can.sell" type="button" :disabled="!!busy" @click="pass"
                                    class="py-4 rounded-xl bg-rose-500/80 text-white font-bold disabled:opacity-30">
                                {{ busy === 'pass' ? 'Passing…' : 'PASS' }}
                            </button>
                        </div>
                        <!-- SELL is disabled with no leader on purpose: there is nobody to sell to. -->
                    </template>

                    <div v-else class="text-center py-10">
                        <p class="text-lg text-slate-400">Nobody on the block.</p>
                        <button v-if="can.control" type="button" :disabled="!!busy" @click="next"
                                class="mt-4 px-6 py-3 rounded-xl bg-brand-500 bg-indigo-500 text-white font-bold disabled:opacity-30">
                            {{ busy === 'next' ? 'Loading…' : 'Next player' }}
                        </button>
                    </div>
                </div>

                <div v-if="cp?.bids?.length" class="rounded-2xl bg-slate-900 p-5">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-3">Recent bids</p>
                    <ul class="space-y-1.5">
                        <li v-for="b in cp.bids" :key="b.id"
                            class="flex items-center justify-between text-sm border-b border-slate-800 pb-1.5">
                            <span class="text-slate-300">{{ b.team ?? '—' }}</span>
                            <span class="font-semibold tabular-nums">{{ money(b.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="can.control" class="flex gap-3">
                    <button type="button" :disabled="!!busy" @click="togglePause"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm font-semibold disabled:opacity-30">
                        {{ paused ? 'Resume auction' : 'Pause auction' }}
                    </button>
                </div>
            </section>

            <aside class="space-y-5">
                <div class="rounded-2xl bg-slate-900 p-4">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-3">Team purses</p>
                    <ul class="space-y-2">
                        <li v-for="t in teams" :key="t.id" class="text-sm">
                            <div class="flex items-center justify-between">
                                <span class="truncate" :class="t.squad_full ? 'text-slate-500' : ''">{{ t.name }}</span>
                                <span class="tabular-nums font-semibold">{{ money(t.remaining_budget) }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ t.players_bought }}/{{ t.slots_required }} · max {{ money(t.max_bid_allowed) }}
                                <span v-if="t.excluded" class="text-amber-400"> · out</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-slate-900 p-4">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-3">Just sold</p>
                    <ul class="space-y-1.5">
                        <li v-for="p in (s.sold_players ?? [])" :key="p.id"
                            class="flex items-center justify-between text-sm">
                            <span class="min-w-0 truncate">
                                {{ p.name }}
                                <span class="text-slate-500 text-xs">{{ p.team }}</span>
                            </span>
                            <span class="tabular-nums font-semibold text-emerald-400 shrink-0 ml-2">
                                {{ money(p.price) }}
                            </span>
                        </li>
                        <li v-if="!(s.sold_players ?? []).length" class="text-xs text-slate-500">Nothing yet.</li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-slate-900 p-4">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-3">
                        Up next<span v-if="queue.length"> · {{ queue.length }} shown</span>
                    </p>
                    <ul class="space-y-1 max-h-72 overflow-y-auto">
                        <li v-for="q in queue" :key="q.id" class="flex items-center justify-between text-sm">
                            <span class="truncate">{{ q.name }}</span>
                            <span class="text-xs text-slate-500 shrink-0 ml-2">{{ money(q.base_price) }}</span>
                        </li>
                    </ul>
                </div>
            </aside>
        </main>
    </div>
</template>
