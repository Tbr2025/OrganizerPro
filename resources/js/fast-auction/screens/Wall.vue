<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { get } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor, priceLabel } from '../lib/money';
import { customElements, elementStyle, isVisible, tableColumns } from '../lib/design';
import { subscribeLocal } from '../lib/local-bus';

const props = defineProps({
    boot: { type: Object, required: true },
});

const snap = ref(props.boot.snapshot ?? {});

/*
 * The design is REACTIVE, not read once.
 *
 * It used to be a const taken from the boot payload, so the template an organizer switched to
 * mid-auction — or the one a pool carries — never reached the projector until somebody reloaded
 * it. The snapshot now carries a thirty-byte key saying which design is in force, and the full
 * twelve-kilobyte design is fetched only when that key actually changes.
 */
const design = ref(props.boot.design ?? {});
const positions = computed(() => design.value.positions ?? {});
const designKey = ref(props.boot.snapshot?.design_key ?? null);

watch(() => snap.value.design_key, async (key) => {
    if (!key || key === designKey.value) return;
    designKey.value = key;

    try {
        design.value = await get(props.boot.urls.design, 'design');
        // The canvas is sized from the design, so a new one has to be re-fitted.
        fit();
    } catch (e) {
        // Keep drawing on the design we have; a wall that blanks itself is worse than one a
        // template behind.
    }
});

const lastBidId = ref(0);
const flash = ref(false);

const active = computed(() => snap.value.active ?? null);
const stage = computed(() => active.value?.stage ?? null);
const sealed = computed(() => active.value?.closed_bid ?? null);
const sold = computed(() => snap.value.sold ?? []);

/*
 * The card outlives the lot.
 *
 * `auctionPlayer` is only ever somebody ON the block, so the instant the hammer fell this wall
 * lost the player entirely and put up a stamp of its own composition instead. That is not what
 * the classic wall does and not what a hall expects: the card STAYS, wearing its SOLD badge and
 * the buyer's crest, so the room goes on looking at the face of the player who just sold rather
 * than at a summary of them.
 *
 * The feed already carries that row as `lastActionPlayer` — the whole settled lot, not a
 * summary — and this is the half the wall was not reading.
 */
const liveRow = computed(() => active.value?.auctionPlayer ?? null);
const settledRow = computed(() => active.value?.lastActionPlayer ?? null);

/*
 * ── How long a result holds the wall ──
 *
 * An ordinary gap between two players keeps the card up indefinitely: the room is looking at who
 * just sold, and blanking that to say "waiting for the next player" tells them nothing they did
 * not already know. But a pool ending, an auction closing or a pause are things the room has to
 * be TOLD, and holding a ten-minute-old card through them is how a wall reads as broken.
 *
 * So the hold is a hold, not a state: once the result has had its ten seconds, a stage worth
 * announcing takes the screen. Both timestamps come from the server — the app runs on Asia/Dubai
 * and the database on UTC, so a browser clock cannot be part of this sum. Lifted from the
 * classic wall, including the list.
 */
const ANNOUNCE_STAGES = ['pool_complete', 'all_done', 'completed', 'not_started', 'paused', 'no_pool'];
const RESULT_HOLD_S = 10;

const heldFor = computed(() => {
    const now = Number(active.value?.server_time ?? 0);
    const at = Number(settledRow.value?.updated_at ?? 0);

    return (now && at) ? now - at : Infinity;
});

const announcing = computed(() => ANNOUNCE_STAGES.includes(stage.value?.key) && heldFor.value >= RESULT_HOLD_S);

/*
 * Nothing else will wake the wall.
 *
 * It stops polling while push is healthy and refetches on events, and the end of a hold is not
 * an event — so without this one-shot the announcement would wait for whatever the organizer
 * did next, which during a break between pools is nothing at all.
 */
let holdTimer = null;

watch([heldFor, announcing], ([held, announce]) => {
    if (announce || !ANNOUNCE_STAGES.includes(stage.value?.key) || !Number.isFinite(held)) return;
    if (holdTimer) return;

    holdTimer = setTimeout(() => {
        holdTimer = null;
        reconcile();
    }, Math.max(500, (RESULT_HOLD_S - held) * 1000 + 250));
});

const row = computed(() => liveRow.value ?? (announcing.value ? null : settledRow.value));
const player = computed(() => row.value?.player ?? null);

/** What happened to the lot on the card — null while it is still being bid for. */
const outcome = computed(() => ({
    sold: 'sold', unsold: 'unsold', skipped: 'skipped',
}[row.value?.status] ?? null));

const price = computed(() => (outcome.value === 'sold' ? row.value?.final_price : row.value?.current_price) ?? null);
const leader = computed(() => row.value?.current_bid_team?.name ?? null);
const buyer = computed(() => row.value?.sold_to_team ?? null);

/*
 * ── The result, announced the way the classic wall announces it ──
 *
 * Three separate things happen when a lot settles, and they are deliberately not one thing:
 *
 *   1. a BADGE lands on the card, at the coordinates the template author placed it — the
 *      organizer's own SOLD or UNSOLD artwork when they have uploaded some;
 *   2. a BANNER names the outcome across the top and fades itself out after seven seconds;
 *   3. the buyer's crest appears, and for a sale only, confetti.
 *
 * All three are keyed on `id:status`, not on a poll: a reconcile every couple of seconds would
 * otherwise restart the animation continuously and the banner would never leave.
 */
const RESULT_BANNER_MS = 7000;

const resultKey = computed(() => (row.value && outcome.value) ? `${row.value.id}:${row.value.status}` : '');

const badgeIn = ref(false);
const bannerUp = ref(false);
const confetti = ref([]);
let bannerTimer = null;

const bannerWord = computed(() => ({
    sold: 'Sold', unsold: 'Unsold', skipped: 'Passed',
}[outcome.value] ?? null));

/*
 * Name the BUYER, not just the player.
 *
 * "Sold — Glenn Maxwell" leaves the one fact the room is waiting for off the loudest thing on
 * the screen. The team is on the card below, but the banner is what people look up at, and a
 * sale nobody can attribute is a sale that gets asked about twice.
 */
const bannerName = computed(() => {
    const name = player.value?.name ?? '';
    const to = buyer.value?.name;

    return (outcome.value === 'sold' && to) ? `${name} \u2192 ${to}` : name;
});

watch(resultKey, (key, was) => {
    if (key === was) return;

    clearTimeout(bannerTimer);

    if (!key) {
        bannerUp.value = false;
        badgeIn.value = false;
        confetti.value = [];

        return;
    }

    // A tick apart so the class is removed and re-added; without it the animation does not
    // restart when one result follows another.
    badgeIn.value = false;
    bannerUp.value = true;
    requestAnimationFrame(() => { badgeIn.value = true; });

    // No poppers for an unsold lot: nothing was bought, and streamers over a player nobody
    // wanted reads as mockery rather than drama. The classic wall makes the same distinction.
    confetti.value = outcome.value === 'sold' ? burst() : [];

    bannerTimer = setTimeout(() => { bannerUp.value = false; }, RESULT_BANNER_MS);
}, { immediate: true });

/*
 * ── "Loading next player" ──
 *
 * The previous player goes down as the loader goes up. The card is repainted underneath while
 * this runs — the push already carries the new player — so without hiding it the sequence is:
 * old face, loader over old face, new face. Hiding it makes the three beats the room should
 * see: the old one leaves, something is coming, the new one arrives.
 */
const LOADER_MS = 1600;
const loadingNext = ref(false);
let loaderTimer = null;
let seenFirstLot = false;

const lotIn = ref(false);
const shownLotId = ref(0);

function enterLot() {
    lotIn.value = false;
    requestAnimationFrame(() => { lotIn.value = true; });
}

watch(() => liveRow.value?.id, (id) => {
    if (!id || id === shownLotId.value) return;

    shownLotId.value = id;

    // The first lot a projector sees is not a CHANGE of player — it is the wall coming up, and
    // a loader there just delays the first card by a second and a half.
    if (!seenFirstLot) {
        seenFirstLot = true;
        enterLot();

        return;
    }

    loadingNext.value = true;
    clearTimeout(loaderTimer);

    loaderTimer = setTimeout(() => {
        loaderTimer = null;
        loadingNext.value = false;
        enterLot();
    }, LOADER_MS);
}, { immediate: true });

/** Whether there is a card to draw at all — a live lot, or a settled one still being held. */
const onBlock = computed(() => Boolean(player.value) && !loadingNext.value);

/** A fixed set of paper scraps, positioned once. Cheap enough for a wall that must not stutter. */
function burst() {
    const colours = ['#22c55e', '#fbbf24', '#38bdf8', '#f472b6', '#ffffff'];
    return Array.from({ length: 60 }, (_, i) => ({
        id: i,
        left: Math.random() * 100,
        delay: Math.random() * 0.35,
        duration: 2.4 + Math.random() * 1.6,
        drift: (Math.random() - 0.5) * 220,
        spin: (Math.random() - 0.5) * 900,
        colour: colours[i % colours.length],
        size: 6 + Math.random() * 8,
    }));
}

/** The organizer's own stamp artwork, when the template carries one for this outcome. */
const badgeArt = computed(() => (outcome.value === 'sold' ? design.value.soldBadge : design.value.unsoldBadge) || null);

const photo = computed(() =>
    player.value?.image_path ? `/storage/${player.value.image_path}` : null);

/** Initials for the photo placeholder. */
const initials = computed(() => (player.value?.name ?? '?')
    .split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase());

/*
 * The base price appears only once the room has bid past it — `base > 0 && live > base`, the
 * classic wall's rule. Worth copying rather than inventing: templates place this element close to
 * the live price precisely because it is absent for most of a lot.
 */
const showBase = computed(() => {
    const base = Number(row.value?.base_price || 0);
    const live = Number(price.value || 0);

    return base > 0 && live > base;
});

/*
 * The design is drawn on a fixed canvas — 1600×900 for the template in use — and every element
 * sits at absolute pixels on it. So the whole canvas is scaled to the viewport rather than
 * anything being re-flowed: that is what makes the organizer's layout hold its proportions on a
 * projector, a laptop and a phone, and it is how the classic wall behaves too.
 */
// Computed, not constants: a template swapped mid-auction can carry a different canvas.
const cw = computed(() => Number(design.value.canvasWidth || 1601));
const ch = computed(() => Number(design.value.canvasHeight || 910));
const scale = ref(1);

function fit() {
    scale.value = Math.min(window.innerWidth / cw.value, window.innerHeight / ch.value);
}

/*
 * The waiting screen — a screen, not a caption.
 *
 * Its own artwork, the auction's rather than the template's, falling back to a dark gradient the
 * way the classic wall's does. Everything on it comes from the server: the stage decides the
 * words, `progress` decides the rail, so the wall cannot show a state the auction is not in.
 */
const waitingArt = computed(() => design.value.waitingBackground || null);
const progress = computed(() => active.value?.progress ?? {});

/* The chip names the pool being played. A pool that has just ENDED is already named in the
   heading, and showing it again underneath as the running pool would contradict it. */
const poolChip = computed(() => (stage.value?.key === 'pool_complete' ? null : progress.value.pool_name || null));

/** Meaningless before anybody has been through the block, so it is not drawn until then. */
const rail = computed(() => {
    const total = Number(progress.value.total || 0);
    const done = Number(progress.value.done || 0);
    const waiting = Number(progress.value.waiting || 0);

    if (!total || !done) return null;

    return {
        pct: Math.min(100, (done / total) * 100).toFixed(1) + '%',
        // Named, because "3 of 17" means nothing to anyone who has not seen the pools screen —
        // and a pool is how a hall follows an evening.
        text: `${progress.value.pool_name ? progress.value.pool_name + ' · ' : ''}${done} of ${total} done · ${waiting} to go`,
    };
});

/* The auction's own waiting artwork, or the classic wall's dark gradient when it has none. */
const waitingStyle = computed(() => (waitingArt.value
    ? { backgroundImage: `url("${waitingArt.value}")`, backgroundSize: 'cover', backgroundPosition: 'center' }
    : { background: 'linear-gradient(135deg,#0a0a0a 0%,#1a1a2e 50%,#0a0a0a 100%)' }));

const canvasStyle = computed(() => ({
    width: `${cw.value}px`,
    height: `${ch.value}px`,
    transform: `scale(${scale.value})`,
    transformOrigin: 'center center',
    /*
     * ONLY while there is a card to frame.
     *
     * This artwork is the card's furniture — a name plate, a price strip, panels drawn to hold a
     * player. With nobody on the block it was still painted and the stage caption printed over
     * the top, so a hall saw an empty name plate under "AUCTION IS LIVE" and read the wall as
     * stuck on a picture. The classic wall puts this background on `.card-container` and hides
     * the whole thing the moment there is no player, which is what makes its waiting screen a
     * screen rather than a caption.
     */
    backgroundImage: (onBlock.value && design.value.background) ? `url("${design.value.background}")` : 'none',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    // Crossfade when the organizer changes the design, rather than a hard cut on a screen the
    // whole room is looking at.
    transition: 'background-image .35s ease',
}));

/** Position + style straight from the template. */
const at = (key, fallback = {}) => elementStyle(positions.value, key, fallback);
const shown = (key) => isVisible(positions.value, key);

const money = moneyFor(props.boot.amountUnit);

// Computed for the same reason the canvas is: a new template brings new columns and images.
const columns = computed(() => tableColumns(positions.value));
const custom = computed(() => customElements(positions.value));

async function reconcile() {
    try {
        snap.value = await get(props.boot.urls.snapshot, 'wall');
    } catch (e) {
        // A wall never goes blank or shows an error to a room: the last known state was true a
        // moment ago, which is what the hall needs.
    }
}

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

    flash.value = true;
    setTimeout(() => { flash.value = false; }, 450);
}

/**
 * A price from the panel on this same machine.
 *
 * Applied with no bid id, because there is no bid yet — the operator has pressed "+" and the
 * request is still in flight. That is exactly why it is worth applying: a wall projected from
 * the same laptop redraws before the request has left it. The push that follows carries a real
 * id and takes over; the reconcile after that corrects anything this got ahead of.
 *
 * Guarded on the player: a message about a lot that is no longer up must not rewrite this one.
 *
 * The PRICE and nothing else. The panel can see a winning team the room is not meant to have yet
 * — a lot-reveal spin withholds it deliberately — so the leader is never carried on this bus and
 * is left to arrive through `bid.raised`, which already respects that.
 */
function applyLocal(data) {
    if (data.type !== 'price') return;
    if (!snap.value.active?.auctionPlayer) return;
    if (data.playerId && Number(data.playerId) !== Number(snap.value.active.auctionPlayer.id)) return;

    snap.value = {
        ...snap.value,
        active: {
            ...snap.value.active,
            auctionPlayer: {
                ...snap.value.active.auctionPlayer,
                current_price: data.price,
            },
        },
    };
}

let stopLocal = () => {};

onMounted(() => {
    fit();
    window.addEventListener('resize', fit);

    // Same machine, no server in the middle. Push still runs for every other screen.
    stopLocal = subscribeLocal(props.boot.auctionId, applyLocal);

    connect({
        auctionId: props.boot.auctionId,
        isSealedActive: () => Boolean(sealed.value?.active),
        // No heartbeat while push is healthy, exactly as the classic wall behaves.
        silentWhenHealthy: true,
        reconcile,
        onFrame: (name, e) => {
            if (name === 'bid.raised') applyRaise(e);
        },
    });
});

onUnmounted(() => {
    window.removeEventListener('resize', fit);
    clearTimeout(bannerTimer);
    clearTimeout(loaderTimer);
    clearTimeout(holdTimer);
    stopLocal();
});
</script>

<template>
    <div class="w-screen h-screen overflow-hidden bg-black flex items-center justify-center select-none">
        <!--
            Paused, over everything.

            The classic wall has had this since the beginning and this one had nothing, so an
            auctioneer stopping for tea left the projector on whatever it was last showing —
            a card, or the stamp of the last lot — while the panel said AUCTION PAUSED. A hall
            reads that as a screen that has stopped working. Fixed to the viewport, not to the
            canvas, so it covers the letterboxing too.
        -->
        <div v-if="stage?.key === 'paused'"
             class="fixed inset-0 z-[9999] flex flex-col items-center justify-center text-center bg-slate-950/85 backdrop-blur-md">
            <div class="text-8xl leading-none mb-4">⏸️</div>
            <p class="text-5xl font-extrabold uppercase tracking-[0.15em] text-white">Auction paused</p>
            <p class="mt-3 text-lg text-slate-300">{{ stage?.subline || 'Please wait — the auction will resume shortly.' }}</p>
        </div>

        <!-- An HTML-mode template owns its whole document and cannot be honoured here. Say so and
             point at the wall that can render it, rather than quietly showing a different design
             from the one the organizer chose. -->
        <div v-if="design.htmlMode" class="text-center text-white p-10">
            <p class="text-3xl font-bold">This auction uses an HTML wall template.</p>
            <p class="mt-3 text-white/60">
                Open the classic wall to display it.
            </p>
            <a :href="boot.urls.classic" class="mt-6 inline-block px-6 py-3 rounded-xl bg-white/10">
                Classic wall
            </a>
        </div>

        <template v-else>
        <!-- The template's canvas, at its own pixel size, scaled to fit. -->
        <div class="relative shrink-0" :style="canvasStyle">
            <!-- The whole card fades and lifts in when a new lot goes up, so the room sees a
                 change of player rather than fields quietly swapping values. -->
            <template v-if="onBlock">
                <div class="lot-enter" :class="{ 'lot-in': lotIn }"></div>

                <!--
                    The card artwork the organizer placed, INSIDE this guard.

                    It used to sit outside it, so between lots the template's panels, dividers and
                    sponsor strip stayed painted under the "waiting to start" heading and the wall
                    read as a frozen picture that had stopped updating. The classic wall hides its
                    whole card layer the moment nobody is on the block
                    (`card-container.classList.add('hidden')` in showWaiting), and the artwork is
                    part of that layer — it frames a player, and with no player to frame it is
                    just leftover paint.
                -->
                <template v-for="el in custom" :key="el.key">
                    <img v-if="el.kind === 'image'" :src="`/storage/${el.path}`" alt=""
                         :style="{ ...at(el.key), ...el.extra }">
                    <div v-else-if="el.kind === 'text'" :style="{ ...at(el.key), ...el.extra }">{{ el.content }}</div>
                    <div v-else :style="{ ...at(el.key), ...el.extra }"></div>
                </template>
                <template v-if="shown('player_image')">
                    <img v-if="photo" :src="photo" alt="" class="object-cover"
                         :style="at('player_image')">
                    <!-- No photo: initials in the same box. A hole where the template put a
                         portrait is more noticeable than a placeholder. -->
                    <div v-else :style="at('player_image')"
                         class="flex items-center justify-center bg-white/10 text-white/40 font-black text-8xl">
                        {{ initials }}
                    </div>
                </template>

                <!--
                    Uppercase, unless the template explicitly asks for something else.

                    As a CLASS, not as a style fallback. The template stores an untouched field as
                    the string 'none', which would win the object merge and leave the name in
                    whatever case a player typed into a form months ago — reading as a mistake
                    beside BASE VALUE in capitals. A class is beaten by any real inline
                    text-transform the template does set, which is exactly the precedence the
                    classic wall gets by writing this declaration before elementStyle().
                -->
                <div v-if="shown('player_name')" :style="at('player_name')"
                     class="whitespace-nowrap uppercase">
                    {{ player.name }}
                </div>

                <div v-if="shown('player_role') && player.player_type?.type"
                     :style="at('player_role')" class="whitespace-nowrap">
                    {{ player.player_type.type }}
                </div>

                <div v-if="shown('playing_team') && player.playing_team_label"
                     :style="at('playing_team')" class="whitespace-nowrap">
                    {{ player.playing_team_label }}
                </div>

                <div v-if="shown('batting_style') && player.batting_profile?.style"
                     :style="at('batting_style')" class="whitespace-nowrap">
                    {{ player.batting_profile.style }}
                </div>

                <div v-if="shown('bowling_style') && player.bowling_profile?.style"
                     :style="at('bowling_style')" class="whitespace-nowrap">
                    {{ player.bowling_profile.style }}
                </div>

                <div v-if="shown('travel_plan') && player.travel_plan_label"
                     :style="at('travel_plan')" class="whitespace-nowrap">
                    {{ player.travel_plan_label }}
                </div>

                <!-- The opening figure, shown only once bidding has passed it — the classic
                     wall's rule (`base > 0 && live > base`). Before the first raise it says
                     nothing, which is why a template can sit it near the live price. -->
                <div v-if="shown('base_price') && showBase" :style="at('base_price')"
                     class="whitespace-nowrap">
                    {{ money(row.base_price) }}
                </div>

                <!-- The price, and the one element that reacts: a raise gives it a beat so it
                     reads from the back of a hall. -->
                <template v-if="!sealed?.active">
                    <!-- BASE VALUE until a team leads, CURRENT BID once one does, SOLD PRICE
                         after the hammer — the classic wall's own wording, because the template
                         author positioned this element expecting those words. -->
                    <div v-if="shown('bid_label')" :style="at('bid_label')" class="whitespace-nowrap">
                        {{ priceLabel(row) }}
                    </div>

                    <div v-if="shown('current_bid')" :style="at('current_bid')"
                         class="whitespace-nowrap transition-transform duration-200"
                         :class="flash ? 'scale-110' : ''">
                        {{ money(price) }}
                    </div>

                    <div v-if="shown('highest_bidder') && leader"
                         :style="at('highest_bidder')" class="whitespace-nowrap">
                        {{ leader }}
                    </div>
                </template>

                <!-- A sealed round says only THAT it is running. The amounts are private until
                     the reveal and this screen faces the whole room. -->
                <div v-else :style="at('current_bid')" class="whitespace-nowrap">
                    SEALED · {{ String(sealed.state ?? '').replace(/_/g, ' ').toUpperCase() }}
                </div>

                <!--
                    The outcome, stamped where the template author put it.

                    `sold_badge` carries the position for BOTH stamps — the classic wall places
                    its unsold badge on the same coordinates, because a card has one place for a
                    stamp and a lot has one outcome. The organizer's own artwork when they have
                    uploaded any; a plain stamp when they have not, rather than nothing at all.
                -->
                <div v-if="outcome && outcome !== 'skipped' && shown('sold_badge')"
                     :style="at('sold_badge', { bottom: 27, left: 112, width: 150, height: 150, zIndex: 9 })"
                     class="grid place-items-center" :class="{ 'badge-in': badgeIn }">
                    <img v-if="badgeArt" :src="badgeArt" alt="" class="w-full h-full object-contain">
                    <div v-else class="stamp" :class="outcome === 'sold' ? 'stamp-sold' : 'stamp-unsold'">
                        <span class="stamp-word">{{ outcome === 'sold' ? 'Sold' : 'Unsold' }}</span>
                        <span class="stamp-sub">{{ outcome === 'sold' ? 'Signed' : 'No bids' }}</span>
                    </div>
                </div>

                <!-- The buyer's crest, at the template's own spot. Only a sale has one. -->
                <img v-if="outcome === 'sold' && buyer?.logo_path && shown('team_logo')"
                     :src="buyer.logo_path" alt="" class="object-contain"
                     :class="{ 'badge-in': badgeIn }" :style="at('team_logo')">

                <!-- The stats table, with the columns the organizer chose in the editor. -->
                <table v-if="shown('stats_table') && columns.length"
                       :style="at('stats_table')" class="border-collapse">
                    <thead>
                        <tr>
                            <th v-for="c in columns" :key="c.field"
                                :style="{ width: c.width, color: c.headerColor || undefined,
                                          backgroundColor: c.headerBg || undefined,
                                          height: positions.stats_table?.headerHeight
                                              ? `${positions.stats_table.headerHeight}px` : undefined }">
                                {{ c.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td v-for="c in columns" :key="c.field"
                                :style="{ color: c.cellColor || undefined,
                                          backgroundColor: c.cellBg || undefined,
                                          padding: positions.stats_table?.cellPadding
                                              ? `${positions.stats_table.cellPadding}px` : undefined }">
                                {{ player[c.field] ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!--
                The outcome, across the top.

                Outside the card guard deliberately — this is the thing a hall looks up at, and
                it has to stay legible while the card underneath is repainted for the next lot.
                Seven seconds and it fades itself out; a banner that needs taking down by hand
                is a banner that is still up an hour later.
            -->
            <div v-if="bannerUp && bannerWord" class="result-banner"
                 :class="outcome === 'sold' ? 'is-sold' : 'is-unsold'">
                <span class="result-word">{{ bannerWord }}</span>
                <span class="result-name">{{ bannerName }}</span>
            </div>

            <!-- Paper, for a sale only. -->
            <div v-if="confetti.length" class="confetti" aria-hidden="true">
                <i v-for="c in confetti" :key="c.id"
                   :style="{ left: c.left + '%', background: c.colour,
                             width: c.size + 'px', height: (c.size * 0.45) + 'px',
                             animationDelay: c.delay + 's', animationDuration: c.duration + 's',
                             '--drift': c.drift + 'px', '--spin': c.spin + 'deg' }"></i>
            </div>

            <!--
                No sold board here, deliberately.

                The template owns the whole canvas: this design fills its bottom band with a
                sponsor strip, and an overlay of recent sales — which is what was here first —
                landed on top of it. A wall that covers a sponsor is worse than a wall without a
                sold list, and the sold board has its own template type and its own screen.
            -->
        </div>

        <!--
            Loading the next player.

            The gap between one lot leaving and the next arriving is a beat the room reads as
            "something is coming". Without it the card swaps face mid-blink and the change reads
            as a glitch. Full-screen, because the previous player goes down as this goes up.
        -->
        <div v-if="loadingNext" class="screen-layer" :style="waitingStyle">
            <div class="loader-mark"></div>
            <p class="mt-6 text-3xl font-bold tracking-wide">Loading next player</p>
            <div class="loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
        </div>

        <!--
            Nobody on the block: the waiting SCREEN.

            Not a caption over the card artwork — that artwork frames a player, and printing
            "AUCTION IS LIVE" across an empty name plate is what made this wall read as a frozen
            picture. Its own background, the auction's rather than the template's, and the words
            come from the server so the wall cannot claim a state the auction is not in.
        -->
        <div v-else-if="!onBlock" class="screen-layer" :style="waitingStyle">
            <div v-if="poolChip" class="pool-chip">
                <span class="pool-dot"></span>
                <span>{{ poolChip }}</span>
                <span class="pool-sub">Now in play</span>
            </div>

            <h1 class="waiting-title">{{ stage?.heading ?? 'PLEASE WAIT' }}</h1>
            <p class="waiting-sub">{{ stage?.subline || boot.auctionName }}</p>

            <div v-if="rail" class="waiting-rail">
                <div class="waiting-rail-track">
                    <div class="waiting-rail-fill" :style="{ width: rail.pct }"></div>
                </div>
                <p class="waiting-rail-text">{{ rail.text }}</p>
            </div>
        </div>
        </template>
    </div>
</template>

<style scoped>
/*
 * Transform and opacity only.
 *
 * This runs on whatever machine drives the projector, often a modest one, and anything that
 * animates layout or paint drops frames on a screen the whole room is looking at. These are the
 * two properties a compositor can handle without touching the main thread.
 */
/* The stamp lands, once, at the coordinates the template gave it. */
.badge-in { animation: badge-land .45s cubic-bezier(.16, 1, .3, 1) backwards; }

@keyframes badge-land {
    from { opacity: 0; transform: rotate(-8deg) scale(2.4); }
    to   { opacity: 1; transform: none; }
}

/* Used only when the organizer has uploaded no artwork of their own. */
.stamp {
    display: grid;
    place-items: center;
    width: 100%; height: 100%;
    border: .5vh solid;
    border-radius: 1vh;
    background: rgba(0, 0, 0, .72);
    transform: rotate(-8deg);
    line-height: 1.1;
}
.stamp-sold   { border-color: #22c55e; color: #4ade80; }
.stamp-unsold { border-color: #f43f5e; color: #fb7185; }
.stamp-word { font-size: 3.2vh; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
.stamp-sub  { font-size: 1.4vh; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; opacity: .8; }

/*
 * The outcome banner. Pinned to the canvas rather than the viewport so a template that fills the
 * screen and one that letterboxes both put it in the same place relative to the card.
 */
.result-banner {
    position: absolute;
    top: 4%;
    left: 50%;
    z-index: 40;
    display: flex;
    align-items: baseline;
    gap: 1.6vh;
    padding: 1.2vh 3.2vh;
    border-radius: 1.2vh;
    background: rgba(2, 6, 23, .82);
    backdrop-filter: blur(6px);
    white-space: nowrap;
    transform: translateX(-50%);
    animation: banner-life 7s ease-out forwards;
}

.result-banner.is-sold   { border: 2px solid #22c55e; box-shadow: 0 0 54px rgba(34, 197, 94, .4); }
.result-banner.is-unsold { border: 2px solid #f43f5e; box-shadow: 0 0 54px rgba(244, 63, 94, .4); }
.result-banner.is-sold .result-word   { color: #4ade80; }
.result-banner.is-unsold .result-word { color: #fb7185; }

.result-word { font-size: 4vh; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.result-name { font-size: 2.6vh; font-weight: 700; color: #e2e8f0; }

@keyframes banner-life {
    0%   { opacity: 0; transform: translate(-50%, -2vh); }
    6%   { opacity: 1; transform: translate(-50%, 0); }
    82%  { opacity: 1; transform: translate(-50%, 0); }
    100% { opacity: 0; transform: translate(-50%, -1vh); }
}

/*
 * A full-screen layer, above the canvas.
 *
 * Fixed rather than absolute, so it covers the letterboxing a template of a different aspect
 * leaves down the sides — the classic wall's waiting screen is fixed for the same reason.
 */
.screen-layer {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #fff;
    overflow: hidden;
}

.waiting-title {
    font-size: 9vh;
    font-weight: 900;
    letter-spacing: .02em;
    text-shadow: 0 0 4vh rgba(255, 255, 255, .28);
    animation: waiting-pulse 2s ease-in-out infinite;
}

@keyframes waiting-pulse {
    0%, 100% { opacity: .62; transform: scale(1); }
    50%      { opacity: 1; transform: scale(1.02); }
}

.waiting-sub { margin-top: 1.6vh; font-size: 3.4vh; color: rgba(226, 232, 240, .72); }

/* "Pool A is selected" — a hall follows an evening by its pools. */
.pool-chip {
    display: flex;
    align-items: center;
    gap: 1.2vh;
    margin-bottom: 2.6vh;
    padding: 1vh 2.4vh;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, .16);
    background: rgba(2, 6, 23, .55);
    font-size: 2.4vh;
    font-weight: 800;
    letter-spacing: .04em;
}
.pool-dot {
    width: 1.2vh; height: 1.2vh;
    border-radius: 9999px;
    background: #22c55e;
    box-shadow: 0 0 1.6vh rgba(34, 197, 94, .8);
    animation: waiting-pulse 1.6s ease-in-out infinite;
}
.pool-sub { font-size: 1.5vh; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; opacity: .55; }

.waiting-rail { margin-top: 3.4vh; width: min(760px, 62vw); }
.waiting-rail-track {
    height: 1.4vh;
    border-radius: 9999px;
    background: rgba(255, 255, 255, .12);
    overflow: hidden;
}
.waiting-rail-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #22c55e, #4ade80);
    transition: width .5s ease;
}
.waiting-rail-text { margin-top: 1.4vh; font-size: 2vh; font-weight: 700; color: rgba(226, 232, 240, .62); }

/* "Loading next player" — a mark that turns and three dots that do not cost a frame. */
.loader-mark {
    width: 9vh; height: 9vh;
    border-radius: 9999px;
    border: .7vh solid rgba(255, 255, 255, .12);
    border-top-color: rgba(255, 255, 255, .85);
    animation: loader-spin .9s linear infinite;
}

@keyframes loader-spin { to { transform: rotate(360deg); } }

.loader-dots { display: flex; gap: 1.1vh; margin-top: 2vh; }
.loader-dots span {
    width: 1.1vh; height: 1.1vh;
    border-radius: 9999px;
    background: rgba(255, 255, 255, .6);
    animation: loader-pulse 1.05s ease-in-out infinite;
}
.loader-dots span:nth-child(2) { animation-delay: .16s; }
.loader-dots span:nth-child(3) { animation-delay: .32s; }

@keyframes loader-pulse {
    0%, 100% { opacity: .25; transform: scale(.8); }
    50%      { opacity: 1; transform: scale(1); }
}

/* Confetti. Fixed count, positioned once, so there is no per-frame work in JavaScript. */
.confetti { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.confetti i {
    position: absolute;
    top: -6vh;
    border-radius: 1px;
    animation-name: fall;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

@keyframes fall {
    to { transform: translate3d(var(--drift), 108vh, 0) rotate(var(--spin)); opacity: 0; }
}

/*
 * The lot entrance is drawn as a full-canvas wash rather than by animating the card, because the
 * card's elements are absolutely positioned from a saved template — moving them would fight the
 * designer's coordinates and land them in the wrong place for the length of the animation.
 */
.lot-enter { position: absolute; inset: 0; pointer-events: none; opacity: 0; }
.lot-enter.lot-in { animation: lot-wash .6s ease-out both; }

@keyframes lot-wash {
    0%   { opacity: 1; background: radial-gradient(circle at 50% 50%, rgba(255,255,255,.22), transparent 62%); }
    100% { opacity: 0; background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0), transparent 62%); }
}

/* A projector is not a phone, but the setting is honoured wherever it is set. */
@media (prefers-reduced-motion: reduce) {
    .badge-in,
    .result-banner,
    .waiting-title,
    .pool-dot { animation: none; opacity: 1; }
    .result-banner { transform: translateX(-50%); }
    .loader-mark { animation-duration: 2.4s; }
    .loader-dots span { animation: none; opacity: .6; }
    .lot-enter.lot-in { animation: none; opacity: 0; }
    .confetti { display: none; }
}
</style>
