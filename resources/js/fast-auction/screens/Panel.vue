<script setup>
/**
 * The organizer panel, laid out like the classic one.
 *
 * Same stage: photo and details on the left, the standing bid and the leading team's purse on
 * the right, team chips beneath, actions along the bottom. What differs is underneath — every
 * change arrives as a push and Vue patches the node that changed, where the Blade panel
 * re-evaluates several thousand Alpine bindings against a full snapshot.
 *
 * No new write path exists for any of this. Sell, pass, next, undo, re-bid, the price steps and
 * the team chips all POST to the endpoints the classic panel already uses, so a raise made here
 * and one made there travel exactly the same road and hit exactly the same guards.
 *
 * Still only on the classic panel, one permanent click away: the sealed-bid desk, the offline
 * bidding desk, pool management, ads, templates and card exports.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { get, post } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor } from '../lib/money';
import { publishLocal } from '../lib/local-bus';

const props = defineProps({
    boot: { type: Object, required: true },
});

const s = ref(props.boot.snapshot ?? {});
const can = props.boot.can ?? { sell: false, control: false };
const urls = props.boot.urls ?? {};
const money = moneyFor(props.boot.amountUnit);

const busy = ref('');
const error = ref('');
const notice = ref('');
const pushUp = ref(false);
const lastBidId = ref(0);
const sellOpen = ref(false);

const cp = computed(() => s.value.current_player ?? null);
const teams = computed(() => s.value.teams ?? []);
const stats = computed(() => s.value.stats ?? {});
const paused = computed(() => s.value.auction_status === 'paused');
const sealedPending = computed(() => Boolean(s.value.sealed_threshold_pending));
const soldBoard = computed(() => s.value.sold_players ?? []);
const pool = computed(() => s.value.active_pool ?? null);
const leaderTeam = computed(() => teams.value.find((t) => t.name === cp.value?.leader) ?? null);

/* The figure on screen, which moves on a press before the server has answered — see step(). */
const shownPrice = ref(null);
const price = computed(() => shownPrice.value ?? cp.value?.current_price ?? 0);

const timerPct = computed(() => {
    const total = Number(s.value.bid_timer_seconds ?? 0);
    const left = Number(s.value.timer_seconds_remaining ?? 0);
    return total > 0 ? Math.max(0, Math.min(100, (left / total) * 100)) : 0;
});

function photo(path) {
    return path ? `/storage/${path}` : null;
}

async function reconcile() {
    try {
        s.value = await get(urls.snapshot, 'panel');
        // The server has spoken; drop any optimistic figure.
        shownPrice.value = null;
        error.value = '';
        announceLocal();
    } catch (e) {
        if (e.name !== 'AbortError') error.value = 'Reconnecting…';
    }
}

/**
 * Mirror a server-confirmed price to the machine's other screens.
 *
 * The PRICE only — never the leader. The wall deliberately withholds the winning team during a
 * lot-reveal spin, and this panel can see a name the room is not meant to have yet; publishing it
 * would put it on a same-machine wall seconds early and spoil the reveal. Who is leading arrives
 * on the wall through `bid.raised`, which is the path that already respects the withholding.
 */
function announceLocal() {
    if (!cp.value) return;
    publishLocal(props.boot.auctionId, {
        type: 'price',
        playerId: cp.value.id,
        price: cp.value.current_price,
    });
}

function applyRaise(e) {
    const id = Number(e.bid_id ?? 0);
    if (id && id <= lastBidId.value) return;
    lastBidId.value = id;
    shownPrice.value = null;

    if (!s.value.current_player) return;

    s.value = {
        ...s.value,
        current_player: {
            ...s.value.current_player,
            current_price: e.current_price,
            leader: e.team_name ?? s.value.current_player.leader,
        },
    };

    announceLocal();
}

/** The server's own message is surfaced verbatim — "over its ceiling" is what needs reading. */
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

// ── Price steps ───────────────────────────────────────────────────────────────
/*
 * Presses are batched and sent once the operator stops, exactly as the classic panel does it:
 * every request costs a fresh TLS handshake to Pusher, so a burst of presses used to put seconds
 * of somebody else's network on the box and walk a sequence of intermediate figures across the
 * wall. The count is sent, never an amount — the server climbs its own ladder.
 */
const MAX_STEPS_PER_REQUEST = 20; // Mirrors the server's `steps` validation; a bigger batch is refused.
let pendingSteps = 0;
let stepTimer = null;

function step(direction) {
    if (!cp.value || !can.control) return;

    if (direction < 0) {
        // Down is a single, deliberate correction — it has its own endpoint and no batching.
        act('step', urls.decreaseBid, { auctionId: props.boot.auctionId, playerID: cp.value.id });
        return;
    }

    pendingSteps += 1;

    // Move the figure on the press, by the increment the server last quoted for this price.
    shownPrice.value = price.value + Number(s.value.bid_increment ?? 0);
    /*
     * Tell any screen on this machine straight away.
     *
     * A wall in another window of the same browser redraws before the request has even left —
     * no handshake, no round trip. Push still goes out for the screens that are not here, and
     * the reconcile still corrects anything this got ahead of.
     */
    publishLocal(props.boot.auctionId, {
        type: 'price',
        playerId: cp.value.id,
        price: shownPrice.value,
    });

    // Flush AT the cap rather than letting the count run past it: a larger batch is rejected
    // outright, which would leave this panel showing a figure the server never heard about.
    if (pendingSteps >= MAX_STEPS_PER_REQUEST) {
        flushSteps();
        return;
    }

    clearTimeout(stepTimer);
    stepTimer = setTimeout(flushSteps, 700);
}

async function flushSteps() {
    clearTimeout(stepTimer);
    stepTimer = null;

    const rungs = pendingSteps;
    if (!rungs) return;
    pendingSteps = 0;

    try {
        await post(urls.addBid, {
            auctionId: props.boot.auctionId,
            playerID: cp.value?.id,
            correction: true,
            steps: rungs,
        });
    } catch (e) {
        error.value = e.message;
    } finally {
        await reconcile();
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
const bidForTeam = (team) => act(`team-${team.id}`, urls.addBid, {
    auctionId: props.boot.auctionId,
    playerID: cp.value?.id,
    teamId: team.id,
});

const clearTeam = () => act('clear', urls.clearBidTeam, {
    auctionId: props.boot.auctionId,
    playerID: cp.value?.id,
});

/* Anything that settles the lot flushes first: the hammer must not fall on a figure the server
   has not been told about. */
async function settle(fn) {
    await flushSteps();
    fn();
}

const sell = () => settle(() => act('sell', urls.sell,
    { auction_player_id: cp.value?.id },
    `Sell ${cp.value?.name} to ${cp.value?.leader} for ${money(price.value)}?`));

const sellTo = (team) => {
    sellOpen.value = false;
    settle(() => act('sell', urls.sellToTeam, {
        auction_player_id: cp.value?.id,
        team_id: team.id,
        amount: price.value,
    }));
};

const pass = () => settle(() => act('pass', urls.pass,
    { auction_player_id: cp.value?.id }, `Pass ${cp.value?.name} with no sale?`));

const next = () => settle(() => act('next', urls.onBid, {}));
const undo = () => settle(() => act('undo', urls.undo, {}, 'Undo the last action?'));
const reBid = () => settle(() => act('rebid', urls.reBid,
    { auction_player_id: cp.value?.id }, `Re-open bidding on ${cp.value?.name}?`));
const togglePause = () => act('pause', urls.togglePause, {});
const toggleTimer = () => act('timer', urls.toggleTimer, {});

function fullscreen() {
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen?.();
}

let heartbeat = null;

onMounted(() => {
    const rt = connect({
        auctionId: props.boot.auctionId,
        isSealedActive: () => sealedPending.value,
        reconcile,
        onFrame: (name, e) => {
            if (name === 'bid.raised') applyRaise(e);
        },
    });

    heartbeat = setInterval(() => { pushUp.value = rt.isConnected(); }, 1000);
    // Nothing pressed may be left unsent when the operator walks away from the screen.
    window.addEventListener('pagehide', flushSteps);
});

onBeforeUnmount(() => {
    clearInterval(heartbeat);
    clearTimeout(stepTimer);
    window.removeEventListener('pagehide', flushSteps);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-950 text-slate-100">
        <!-- ── Status bar ───────────────────────────────────────────────── -->
        <header class="flex items-center gap-3 px-5 py-2.5 border-b border-slate-800 shrink-0">
            <span class="w-2.5 h-2.5 rounded-full shrink-0"
                  :class="paused ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse'"></span>

            <div class="min-w-0">
                <p class="text-sm font-semibold truncate">{{ boot.auctionName }}</p>
                <p class="text-[11px] text-slate-400 truncate">
                    {{ pool?.name ?? 'No active pool' }}
                    <span v-if="s.pool_progress"> · lot {{ s.pool_progress.done }}/{{ s.pool_progress.total }}</span>
                    · {{ stats.sold_count ?? 0 }} sold · {{ stats.waiting_count ?? 0 }} waiting
                </p>
            </div>

            <span v-if="paused" class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-full bg-amber-500/20 text-amber-300">Paused</span>

            <div class="ml-auto flex items-center gap-2">
                <span v-if="s.timer_enabled && s.timer_seconds_remaining != null"
                      class="text-lg font-bold tabular-nums"
                      :class="s.timer_seconds_remaining <= 5 ? 'text-rose-400' : 'text-slate-200'">
                    {{ s.timer_seconds_remaining }}s
                </span>
                <span class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-full"
                      :class="pushUp ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'">
                    {{ pushUp ? 'Live' : 'Slow link' }}
                </span>
            </div>
        </header>

        <div v-if="s.timer_enabled" class="h-1 bg-slate-800 shrink-0">
            <div class="h-full transition-all duration-1000 ease-linear"
                 :class="s.timer_seconds_remaining <= 5 ? 'bg-rose-500' : 'bg-blue-500'"
                 :style="{ width: timerPct + '%' }"></div>
        </div>

        <p v-if="error" class="px-5 py-2 text-xs bg-rose-500/10 text-rose-300 shrink-0">{{ error }}</p>
        <p v-else-if="notice" class="px-5 py-2 text-xs bg-emerald-500/10 text-emerald-300 shrink-0">{{ notice }}</p>
        <p v-if="!can.sell && !can.control" class="px-5 py-2 text-xs bg-slate-800/60 text-slate-400 shrink-0">
            Read-only — you can watch this auction but not call it.
        </p>

        <!-- ── Stage ────────────────────────────────────────────────────── -->
        <main class="flex-1 min-h-0 flex items-center px-6 lg:px-10 py-6">
            <div v-if="cp" class="w-full flex flex-col lg:flex-row items-stretch gap-8">
                <!-- Photo + details -->
                <div class="flex-1 flex items-center gap-6 lg:gap-10 min-w-0">
                    <div class="w-40 h-52 lg:w-56 lg:h-72 shrink-0 rounded-2xl overflow-hidden bg-slate-800 border-2 border-slate-700 shadow-2xl">
                        <img v-if="photo(cp.image_path)" :src="photo(cp.image_path)" :alt="cp.name"
                             class="w-full h-full object-cover">
                        <div v-else class="w-full h-full grid place-items-center text-5xl font-black text-slate-600">
                            {{ (cp.name ?? '?').charAt(0) }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p v-if="cp.lot_number" class="text-xs uppercase tracking-widest text-slate-500">Lot {{ cp.lot_number }}</p>
                        <h1 class="text-3xl lg:text-5xl font-black leading-tight truncate">{{ cp.name }}</h1>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="cp.player_type" class="text-xs px-2.5 py-1 rounded-full bg-blue-500/15 text-blue-300">{{ cp.player_type }}</span>
                            <span v-if="cp.batting_style" class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-300">{{ cp.batting_style }}</span>
                            <span v-if="cp.bowling_style" class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-300">{{ cp.bowling_style }}</span>
                            <span v-if="cp.is_capped" class="text-xs px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-300">Capped</span>
                        </div>

                        <p class="mt-4 text-sm text-slate-400">
                            Base <span class="text-slate-200 font-semibold">{{ money(cp.base_price) }}</span>
                            <span v-if="cp.location"> · {{ cp.location }}</span>
                        </p>
                    </div>
                </div>

                <!-- Standing bid -->
                <div class="w-full lg:w-96 shrink-0 flex flex-col justify-center">
                    <div class="rounded-2xl border border-slate-700 bg-slate-900/60 p-6 text-center">
                        <p class="text-xs uppercase tracking-widest text-slate-500">Current bid</p>
                        <p class="mt-1 text-4xl lg:text-5xl font-black tabular-nums">{{ money(price) }}</p>

                        <p class="mt-2 text-sm" :class="cp.leader ? 'text-emerald-300' : 'text-slate-500'">
                            {{ cp.leader ?? 'No bids yet' }}
                        </p>

                        <div v-if="can.control" class="mt-4 flex items-center justify-center gap-2">
                            <button type="button" @click="step(-1)" :disabled="!!busy"
                                    class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-600 text-xl font-bold disabled:opacity-30"
                                    title="Lower the price one step">−</button>
                            <button type="button" @click="step(1)"
                                    class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-600 text-xl font-bold"
                                    title="Raise one step, same leading team">+</button>
                        </div>

                        <button v-if="can.control && cp.leader" type="button" @click="clearTeam" :disabled="!!busy"
                                class="mt-3 text-[10px] uppercase tracking-wider text-slate-500 hover:text-amber-300 disabled:opacity-30">
                            Wrong team?
                        </button>

                        <div v-if="leaderTeam" class="mt-4 pt-4 border-t border-slate-800 text-sm text-slate-400">
                            <p>Budget left <span class="text-slate-200">{{ money(leaderTeam.remaining_budget) }}</span></p>
                            <p>Players <span class="text-slate-200">{{ leaderTeam.players_bought ?? 0 }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="w-full text-center text-slate-500">
                <p class="text-2xl font-semibold">Nobody on the block</p>
                <p class="mt-2 text-sm">Press <span class="text-slate-300">Next player</span> to put the next lot up.</p>
            </div>
        </main>

        <!-- ── Team chips ───────────────────────────────────────────────── -->
        <section v-if="cp && can.control" class="px-6 lg:px-10 pb-3 shrink-0">
            <div class="flex flex-wrap gap-2">
                <button v-for="team in teams" :key="team.id" type="button"
                        @click="bidForTeam(team)"
                        :disabled="!!busy || team.excluded || team.squad_full"
                        :title="team.exclusion_reason || `Bid for ${team.name}`"
                        class="flex items-center gap-2 px-3 py-2 rounded-xl border text-sm transition disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="team.name === cp.leader
                            ? 'border-emerald-500 bg-emerald-500/10 text-emerald-200'
                            : 'border-slate-700 bg-slate-900 hover:bg-slate-800'">
                    <img v-if="team.logo_url" :src="team.logo_url" alt="" class="w-6 h-6 rounded-full object-cover">
                    <span class="font-semibold">{{ team.name }}</span>
                    <span class="text-[11px] text-slate-400 tabular-nums">{{ money(team.remaining_budget) }}</span>
                </button>
            </div>
        </section>

        <!-- ── Toolbar ──────────────────────────────────────────────────── -->
        <footer class="border-t border-slate-800 px-6 lg:px-10 py-3 flex flex-wrap items-center gap-2 shrink-0">
            <template v-if="can.sell">
                <button type="button" @click="sell" :disabled="!!busy || !cp || !cp.leader"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold disabled:opacity-30">
                    Sell
                </button>
                <button type="button" @click="sellOpen = true" :disabled="!!busy || !cp"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30"
                        title="Sell to a team that is not the current leader">Sell to…</button>
                <button type="button" @click="pass" :disabled="!!busy || !cp"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 border border-slate-600 font-semibold disabled:opacity-30">
                    Pass
                </button>
            </template>

            <template v-if="can.control">
                <button type="button" @click="next" :disabled="!!busy"
                        class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold disabled:opacity-30">
                    Next player
                </button>
                <button type="button" @click="reBid" :disabled="!!busy || !cp"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">Re-bid</button>
                <button type="button" @click="undo" :disabled="!!busy || !s.can_undo"
                        :title="s.next_undo_notes || 'Undo the last action'"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">Undo</button>
            </template>

            <div class="ml-auto flex items-center gap-2">
                <button v-if="can.control" type="button" @click="toggleTimer" :disabled="!!busy"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    {{ s.timer_enabled ? 'Timer on' : 'Timer off' }}
                </button>
                <button v-if="can.control" type="button" @click="togglePause" :disabled="!!busy"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    {{ paused ? 'Resume' : 'Pause' }}
                </button>
                <button type="button" @click="fullscreen"
                        class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm">Fullscreen</button>
                <a :href="urls.classic" class="px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-600 text-sm">Classic panel</a>
            </div>
        </footer>

        <!-- ── Recent sales ─────────────────────────────────────────────── -->
        <section v-if="soldBoard.length" class="border-t border-slate-800 px-6 lg:px-10 py-2 shrink-0 overflow-x-auto">
            <div class="flex gap-4 text-[11px] text-slate-400 whitespace-nowrap">
                <span v-for="sale in soldBoard" :key="sale.id">
                    <span class="text-slate-200">{{ sale.name }}</span>
                    <span v-if="sale.team"> → {{ sale.team }}</span>
                    <span v-if="sale.price" class="tabular-nums"> {{ money(sale.price) }}</span>
                </span>
            </div>
        </section>

        <!-- ── Sell to a named team ─────────────────────────────────────── -->
        <div v-if="sellOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
             @click.self="sellOpen = false">
            <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-700 p-5">
                <h2 class="font-bold">Sell {{ cp?.name }} for {{ money(price) }}</h2>
                <p class="mt-1 text-xs text-slate-400">Choose the buying team.</p>

                <div class="mt-4 max-h-72 overflow-y-auto space-y-1.5">
                    <button v-for="team in teams" :key="team.id" type="button"
                            @click="sellTo(team)" :disabled="team.excluded || team.squad_full"
                            :title="team.exclusion_reason || ''"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-left disabled:opacity-30 disabled:cursor-not-allowed">
                        <img v-if="team.logo_url" :src="team.logo_url" alt="" class="w-6 h-6 rounded-full object-cover">
                        <span class="font-semibold text-sm">{{ team.name }}</span>
                        <span class="ml-auto text-[11px] text-slate-400 tabular-nums">{{ money(team.remaining_budget) }}</span>
                    </button>
                </div>

                <button type="button" @click="sellOpen = false"
                        class="mt-4 w-full px-3 py-2 rounded-lg bg-slate-800 border border-slate-600 text-sm">Cancel</button>
            </div>
        </div>
    </div>
</template>
