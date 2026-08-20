<script setup>
import { computed, onMounted, ref } from 'vue';
import { get, post } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor } from '../lib/money';

const props = defineProps({
    boot: { type: Object, required: true },
});

/*
 * The snapshot the server inlined into the page. The first paint costs no requests at all —
 * which is the whole reason the shell is served by Laravel rather than as a static file.
 */
const active = ref(props.boot.snapshot?.active ?? null);
const purse = ref(props.boot.snapshot?.purse ?? null);
const sold = ref(props.boot.snapshot?.sold ?? []);
const sealed = ref(props.boot.snapshot?.sealed ?? null);

const pushUp = ref(false);
const busy = ref(false);
const error = ref('');
const lastBidId = ref(0);

/*
 * The payload nests the row under `auctionPlayer`, with the player inside that — the shape the
 * public active-player feed has always had, which this reuses rather than reshaping. Reading it
 * here in one place keeps the rest of the template from knowing about it.
 */
const row = computed(() => active.value?.auctionPlayer ?? null);
const player = computed(() => row.value?.player ?? null);
const onBlock = computed(() => Boolean(active.value?.success && player.value));
const price = computed(() => row.value?.current_price ?? null);
const leader = computed(() => row.value?.current_bid_team?.name ?? null);
const photo = computed(() =>
    player.value?.image_path ? `/storage/${player.value.image_path}` : null);
const sealedActive = computed(() => Boolean(sealed.value?.active));

const money = moneyFor(props.boot.amountUnit);

/** Refetch the whole screen from the one snapshot endpoint. */
async function reconcile() {
    try {
        const data = await get(props.boot.urls.snapshot, 'snapshot');

        active.value = data.active ?? null;
        purse.value = data.purse ?? null;
        sold.value = data.sold ?? [];
        sealed.value = data.sealed ?? null;
        error.value = '';
    } catch (e) {
        if (e.name !== 'AbortError') {
            // Never blank the screen on a failed refresh: the last known good state is far more
            // useful to a manager mid-lot than an error page.
            error.value = 'Reconnecting…';
        }
    }
}

/**
 * A raise, applied straight from the frame.
 *
 * Guarded on a monotonic bid_id: frames can arrive out of order, and letting an older one
 * through would walk the price backwards on screen while the room is shouting a higher number.
 */
function applyRaise(e) {
    const id = Number(e.bid_id ?? 0);

    if (id && id <= lastBidId.value) {
        return;
    }

    lastBidId.value = id;

    if (active.value?.auctionPlayer) {
        active.value = {
            ...active.value,
            auctionPlayer: {
                ...active.value.auctionPlayer,
                current_price: e.current_price,
                current_bid_team_id: e.current_bid_team_id,
                current_bid_team: e.team_name
                    ? { name: e.team_name }
                    : active.value.auctionPlayer.current_bid_team,
            },
        };
    }
}

async function placeBid() {
    if (busy.value || !onBlock.value) return;

    busy.value = true;
    error.value = '';

    try {
        await post(props.boot.urls.placeBid, { auction_player_id: row.value.id });
        await reconcile();
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    const rt = connect({
        auctionId: props.boot.auctionId,
        isSealedActive: () => sealedActive.value,
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
        <header class="flex items-center gap-3 px-4 py-3 border-b border-slate-800">
            <div class="min-w-0">
                <p class="text-sm font-semibold truncate">{{ boot.teamName }}</p>
                <p class="text-xs text-slate-400 truncate">{{ boot.auctionName }}</p>
            </div>
            <span class="ml-auto text-[10px] uppercase tracking-wide px-2 py-1 rounded-full"
                  :class="pushUp ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'">
                {{ pushUp ? 'Live' : 'Slow link' }}
            </span>
            <a :href="boot.urls.classic"
               class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-full bg-slate-800 text-slate-300">
                Classic
            </a>
        </header>

        <p v-if="error" class="px-4 py-2 text-xs bg-amber-500/10 text-amber-300">{{ error }}</p>

        <main class="p-4 space-y-4">
            <!-- Purse first: it is what decides whether the manager may press the button at all. -->
            <section v-if="purse" class="grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-slate-900 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">Remaining</p>
                    <p class="text-lg font-bold tabular-nums">{{ money(purse.remaining_budget) }}</p>
                </div>
                <div class="rounded-xl bg-slate-900 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">Max bid</p>
                    <p class="text-lg font-bold tabular-nums">{{ money(purse.max_bid_allowed) }}</p>
                </div>
                <div class="rounded-xl bg-slate-900 p-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">Squad</p>
                    <p class="text-lg font-bold tabular-nums">{{ purse.slots_filled }}/{{ purse.slots_required }}</p>
                </div>
            </section>

            <section v-if="onBlock" class="rounded-2xl bg-slate-900 p-4">
                <div class="flex items-center gap-3">
                    <img v-if="photo" :src="photo" alt=""
                         class="w-16 h-16 rounded-full object-cover bg-slate-800">
                    <div class="min-w-0">
                        <p class="text-xl font-bold truncate">{{ player.name }}</p>
                        <p class="text-xs text-slate-400">{{ player.player_type?.type ?? '' }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-end justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Current</p>
                        <p class="text-3xl font-black tabular-nums">{{ money(price) }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ leader ?? 'No bids yet' }}
                        </p>
                    </div>
                    <div v-if="purse?.next_bid_amount" class="text-right">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Your bid</p>
                        <p class="text-xl font-bold tabular-nums text-emerald-400">
                            {{ money(purse.next_bid_amount) }}
                        </p>
                    </div>
                </div>

                <button v-if="!sealedActive" type="button" :disabled="busy" @click="placeBid"
                        class="mt-4 w-full py-4 rounded-xl bg-emerald-500 text-slate-950 text-lg font-black disabled:opacity-40">
                    {{ busy ? 'Placing…' : 'BID' }}
                </button>

                <!-- A sealed round is a private number, not a raise. The open-bid button must not
                     be reachable while one is live. -->
                <p v-else class="mt-4 text-center text-sm text-indigo-300 bg-indigo-500/10 rounded-xl py-3">
                    Sealed round in progress — {{ sealed.state }}
                </p>
            </section>

            <section v-else class="rounded-2xl bg-slate-900 p-8 text-center text-slate-400">
                <p class="text-sm">Waiting for the next player…</p>
            </section>

            <section v-if="sold.length">
                <p class="text-[10px] uppercase tracking-wide text-slate-500 mb-2">
                    My squad ({{ sold.length }})
                </p>
                <ul class="space-y-1.5">
                    <li v-for="s in sold" :key="s.id"
                        class="flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2">
                        <img v-if="s.player?.image" :src="s.player.image" alt=""
                             class="w-8 h-8 rounded-full object-cover bg-slate-800">
                        <span class="text-sm truncate">{{ s.player?.name }}</span>
                        <span class="ml-auto text-sm font-semibold tabular-nums">{{ money(s.final_price) }}</span>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
