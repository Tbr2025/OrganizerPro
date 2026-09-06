<script setup>
/**
 * The strip along the bottom of a broadcast: who is up, what they are at, who is leading, and
 * what has just gone.
 *
 * Kept to one band on purpose. The classic ticker is 1,840 lines of Blade because it grew into a
 * second wall; this is the thing that is actually useful on a stream or a side monitor, and it
 * has to survive being left open for six hours on a stick PC.
 *
 * Push-driven with the module's usual reconcile behind it. Nothing here polls on its own.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { get } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor } from '../lib/money';

const props = defineProps({
    boot: { type: Object, required: true },
});

const s = ref(props.boot.snapshot ?? {});
const money = moneyFor(props.boot.amountUnit);
const lastBidId = ref(0);
const bumped = ref(false);

const cp = computed(() => s.value.current_player ?? null);
const stage = computed(() => s.value.stage ?? null);
const sales = computed(() => s.value.recent_sales ?? []);
const live = computed(() => Boolean(cp.value));

async function reconcile() {
    try {
        s.value = await get(props.boot.urls.snapshot, 'ticker');
    } catch (e) {
        // A ticker that blanks itself on a hiccup is worse than one showing the last good frame.
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
            current_bid_team: e.team_name
                ? { ...(s.value.current_player.current_bid_team ?? {}), name: e.team_name }
                : s.value.current_player.current_bid_team,
        },
    };

    // A one-shot pulse on the figure, so a raise is visible on a screen nobody is watching
    // closely — which is the whole job of a ticker.
    bumped.value = false;
    requestAnimationFrame(() => { bumped.value = true; });
}

let rt = null;

onMounted(() => {
    rt = connect({
        auctionId: props.boot.auctionId,
        // No heartbeat while push is healthy: this screen is left open for hours and a request a
        // second from every stick PC in the building adds up to nothing useful.
        silentWhenHealthy: true,
        reconcile,
        onFrame: (name, e) => {
            if (name === 'bid.raised') applyRaise(e);
        },
    });
});

onUnmounted(() => rt?.stop());
</script>

<template>
    <div class="ticker">
        <div class="ticker-brand">{{ boot.auctionName }}</div>

        <template v-if="live">
            <div class="ticker-lot">
                <span class="ticker-label">On the block</span>
                <span class="ticker-name">{{ cp.player?.name ?? cp.name }}</span>
            </div>

            <div class="ticker-price" :class="{ bump: bumped }">
                {{ money(cp.current_price) }}
            </div>

            <div class="ticker-leader">
                <span v-if="cp.current_bid_team?.name">{{ cp.current_bid_team.name }}</span>
                <span v-else class="ticker-quiet">No bids</span>
            </div>
        </template>

        <div v-else class="ticker-stage">{{ stage?.heading ?? 'PLEASE WAIT' }}</div>

        <!-- Recent sales, scrolling. Duplicated once so the loop has no seam; a marquee that
             restarts visibly is the thing that makes a ticker look cheap. -->
        <div v-if="sales.length" class="ticker-marquee">
            <div class="ticker-track">
                <span v-for="(sale, i) in [...sales, ...sales]" :key="i" class="ticker-sale">
                    <b>{{ sale.name ?? sale.player?.name }}</b>
                    <em v-if="sale.team ?? sale.sold_to_team?.name">{{ sale.team ?? sale.sold_to_team?.name }}</em>
                    <u>{{ money(sale.price ?? sale.final_price) }}</u>
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * One row, fixed height, no layout that depends on content length — a ticker that reflows when a
 * long name arrives judders on every raise.
 */
.ticker {
    display: flex;
    align-items: stretch;
    height: 100vh;
    min-height: 64px;
    background: #05060a;
    color: #fff;
    font-family: ui-sans-serif, system-ui, sans-serif;
    overflow: hidden;
}

.ticker > * { display: flex; align-items: center; padding: 0 1.4rem; white-space: nowrap; }

.ticker-brand {
    background: #e11d48;
    font-weight: 900;
    letter-spacing: .04em;
    font-size: clamp(14px, 2.6vh, 26px);
}

.ticker-label {
    font-size: clamp(9px, 1.4vh, 13px);
    text-transform: uppercase;
    letter-spacing: .12em;
    opacity: .45;
    margin-right: .7rem;
}

.ticker-name { font-weight: 800; font-size: clamp(15px, 3vh, 30px); }

.ticker-price {
    font-weight: 900;
    font-size: clamp(18px, 3.6vh, 38px);
    color: #22c55e;
    font-variant-numeric: tabular-nums;
}

/* Transform only — a raise must not cost a layout pass. */
.ticker-price.bump { animation: bump .4s cubic-bezier(.16, 1, .3, 1); }
@keyframes bump { 0% { transform: scale(1); } 45% { transform: scale(1.16); } 100% { transform: scale(1); } }

.ticker-leader { font-size: clamp(13px, 2.4vh, 24px); opacity: .85; }
.ticker-quiet { opacity: .4; }
.ticker-stage { font-weight: 900; font-size: clamp(15px, 3vh, 30px); opacity: .8; }

.ticker-marquee { flex: 1; min-width: 0; overflow: hidden; padding: 0; border-left: 1px solid rgba(255,255,255,.1); }
.ticker-track { display: flex; align-items: center; gap: 2.4rem; padding-left: 2.4rem; animation: scroll 38s linear infinite; }
@keyframes scroll { to { transform: translateX(-50%); } }

.ticker-sale { display: inline-flex; align-items: baseline; gap: .6rem; font-size: clamp(12px, 2.1vh, 21px); }
.ticker-sale b { font-weight: 700; }
.ticker-sale em { font-style: normal; opacity: .55; }
.ticker-sale u { text-decoration: none; color: #22c55e; font-weight: 800; font-variant-numeric: tabular-nums; }

@media (prefers-reduced-motion: reduce) {
    .ticker-track { animation: none; }
    .ticker-price.bump { animation: none; }
}
</style>
