<script setup>
import { computed, onMounted, ref } from 'vue';
import { get } from '../lib/api';
import { connect } from '../lib/realtime';

const props = defineProps({
    boot: { type: Object, required: true },
});

const snap = ref(props.boot.snapshot ?? {});
const lastBidId = ref(0);
const flash = ref(false);

const active = computed(() => snap.value.active ?? null);
const row = computed(() => active.value?.auctionPlayer ?? null);
const player = computed(() => row.value?.player ?? null);
const onBlock = computed(() => Boolean(active.value?.success && player.value));

const price = computed(() => row.value?.current_price ?? null);
const leader = computed(() => row.value?.current_bid_team?.name ?? null);
const stage = computed(() => active.value?.stage ?? null);
const progress = computed(() => active.value?.progress ?? null);
const sealed = computed(() => active.value?.closed_bid ?? null);
const sold = computed(() => snap.value.sold ?? []);

const photo = computed(() =>
    player.value?.image_path ? `/storage/${player.value.image_path}` : null);

/** The unit the organizer chose — Points, Coins, dollars — never hardcoded. */
const money = (v) => {
    if (v === null || v === undefined || v === '') return '—';
    const unit = props.boot.amountUnit ?? { label: 'Points', prefix: false };
    const n = Number(v);
    const figure = n >= 1e7 ? (n / 1e7).toFixed(2).replace(/\.?0+$/, '') + 'Cr'
        : n >= 1e5 ? (n / 1e5).toFixed(2).replace(/\.?0+$/, '') + 'L'
        : n.toLocaleString();

    return unit.prefix ? `${unit.label}${figure}` : `${figure} ${unit.label}`;
};

async function reconcile() {
    try {
        const data = await get(props.boot.urls.snapshot, 'wall');
        snap.value = data;
    } catch (e) {
        // A wall must never go blank or show an error to a room. Holding the last known state is
        // always better: the price on screen was true a moment ago, which is what people need.
    }
}

/**
 * A raise, straight from the frame — this is what the room is watching.
 *
 * Ordered on the monotonic bid_id so a late frame cannot walk the price backwards on a screen
 * everybody is looking at.
 */
function applyRaise(e) {
    const id = Number(e.bid_id ?? 0);
    if (id && id <= lastBidId.value) return;
    lastBidId.value = id;

    if (!snap.value.active?.auctionPlayer) return;

    snap.value = {
        ...snap.value,
        active: {
            ...snap.value.active,
            auctionPlayer: {
                ...snap.value.active.auctionPlayer,
                current_price: e.current_price,
                current_bid_team_id: e.current_bid_team_id,
                current_bid_team: e.team_name
                    ? { name: e.team_name }
                    : snap.value.active.auctionPlayer.current_bid_team,
            },
        },
    };

    // A visible beat on the price, so a raise reads from the back of the hall.
    flash.value = true;
    setTimeout(() => { flash.value = false; }, 450);
}

onMounted(() => {
    connect({
        auctionId: props.boot.auctionId,
        isSealedActive: () => Boolean(sealed.value?.active),
        reconcile,
        onFrame: (name, e) => {
            if (name === 'bid.raised') applyRaise(e);
        },
    });
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white overflow-hidden select-none">
        <!-- Header: what competition, and where the evening has got to. -->
        <header class="flex items-center gap-6 px-10 py-5 border-b border-white/10">
            <img v-if="snap.tournamentLogo" :src="snap.tournamentLogo" alt=""
                 class="h-14 w-14 object-contain">
            <div class="min-w-0">
                <p class="text-2xl font-bold truncate">{{ boot.tournamentName ?? boot.auctionName }}</p>
                <p class="text-sm text-white/50 truncate">{{ stage?.subline ?? boot.auctionName }}</p>
            </div>
            <div v-if="progress" class="ml-auto flex items-center gap-8 text-right">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-white/40">Sold</p>
                    <p class="text-3xl font-black tabular-nums text-emerald-400">{{ progress.sold }}</p>
                </div>
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-white/40">Remaining</p>
                    <p class="text-3xl font-black tabular-nums">{{ progress.waiting }}</p>
                </div>
            </div>
        </header>

        <!-- Fills the projector. A wall that stops two thirds of the way down reads as broken
             from the back of a hall, so the grid takes the remaining viewport height and the
             player card centres inside it. -->
        <main class="grid grid-cols-3 gap-8 p-10 h-[calc(100vh-6.5rem)]">
            <!-- The player on the block: two thirds of the wall, because it is the only thing
                 the room is actually looking at. -->
            <section class="col-span-2 flex flex-col">
                <div v-if="onBlock" class="flex-1 flex flex-col justify-center rounded-3xl bg-gradient-to-br from-slate-900 to-slate-900/40 p-12">
                    <div class="flex items-center gap-8">
                        <img v-if="photo" :src="photo" alt=""
                             class="h-44 w-44 rounded-2xl object-cover bg-white/5">
                        <div class="min-w-0">
                            <p class="text-6xl font-black leading-tight truncate">{{ player.name }}</p>
                            <p class="mt-2 text-2xl text-white/50">
                                {{ player.player_type?.type ?? '' }}
                                <span v-if="player.is_wicket_keeper"> · WK</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="!sealed?.active" class="mt-10 flex items-end justify-between gap-8">
                        <div>
                            <p class="text-sm uppercase tracking-widest text-white/40">Current bid</p>
                            <p class="text-8xl font-black tabular-nums transition-transform duration-200"
                               :class="flash ? 'scale-105 text-emerald-400' : ''">
                                {{ money(price) }}
                            </p>
                        </div>
                        <div class="text-right pb-4">
                            <p class="text-sm uppercase tracking-widest text-white/40">Leading</p>
                            <p class="text-4xl font-bold truncate max-w-md">
                                {{ leader ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- A sealed round shows THAT it is happening and nothing more. The amounts
                         are private until the reveal, and this screen faces the whole room. -->
                    <div v-else class="mt-10 rounded-2xl bg-indigo-500/10 border border-indigo-400/20 p-8 text-center">
                        <p class="text-sm uppercase tracking-widest text-indigo-300">Sealed round</p>
                        <p class="mt-2 text-5xl font-black text-indigo-200">
                            {{ String(sealed.state ?? '').replace(/_/g, ' ').toUpperCase() }}
                        </p>
                        <p v-if="sealed.round_number" class="mt-2 text-xl text-indigo-300/70">
                            Round {{ sealed.round_number }}
                        </p>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col justify-center rounded-3xl bg-slate-900 p-20 text-center">
                    <p class="text-5xl font-black text-white/80">{{ stage?.heading ?? 'Please wait' }}</p>
                    <p v-if="stage?.subline" class="mt-4 text-2xl text-white/40">{{ stage.subline }}</p>
                </div>
            </section>

            <!-- Recently sold. The board grows all evening; the room cares about the last dozen. -->
            <aside class="flex flex-col min-h-0">
                <p class="text-[11px] uppercase tracking-widest text-white/40 mb-3">
                    Recently sold<span v-if="snap.soldTotal"> · {{ snap.soldTotal }} total</span>
                </p>
                <ul class="space-y-2 overflow-y-auto min-h-0">
                    <li v-for="s in sold" :key="s.id"
                        class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-semibold truncate">{{ s.player?.name }}</span>
                            <span class="block text-xs text-white/40 truncate">{{ s.sold_to_team?.name }}</span>
                        </span>
                        <span class="text-lg font-bold tabular-nums text-emerald-400">
                            {{ money(s.final_price) }}
                        </span>
                    </li>
                    <li v-if="!sold.length" class="text-white/30 text-sm px-4 py-3">
                        Nothing sold yet.
                    </li>
                </ul>
            </aside>
        </main>
    </div>
</template>
