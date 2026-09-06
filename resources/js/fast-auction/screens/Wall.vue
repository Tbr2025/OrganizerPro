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
const row = computed(() => active.value?.auctionPlayer ?? null);
const player = computed(() => row.value?.player ?? null);
const onBlock = computed(() => Boolean(active.value?.success && player.value));
const price = computed(() => row.value?.current_price ?? null);
const leader = computed(() => row.value?.current_bid_team?.name ?? null);
const stage = computed(() => active.value?.stage ?? null);
const sealed = computed(() => active.value?.closed_bid ?? null);
const sold = computed(() => snap.value.sold ?? []);

/*
 * The lot that just settled, and whether its stamp has been shown yet.
 *
 * `activePlayer` only ever returns somebody ON the block, so between lots the wall had nothing
 * to say and a sale passed without a mark. The id is what makes the animation play ONCE: a
 * reconcile every couple of seconds would otherwise restart it continuously.
 */
const result = computed(() => snap.value.result ?? null);
const shownResultId = ref(0);
const sealIn = ref(false);
const confetti = ref([]);

watch(result, (next) => {
    if (!next || next.id === shownResultId.value) return;

    shownResultId.value = next.id;
    sealIn.value = false;

    // A tick apart so the class is removed and re-added; without it the animation does not
    // restart when one result follows another.
    requestAnimationFrame(() => { sealIn.value = true; });

    confetti.value = next.outcome === 'sold' ? burst() : [];
}, { immediate: true });

/*
 * A new lot arriving gets its own entrance.
 *
 * Keyed on the auction_player id rather than on the name: two players can share a name, and a
 * price change must not re-trigger the animation mid-lot.
 */
const lotIn = ref(false);
const shownLotId = ref(0);

watch(() => row.value?.id, (id) => {
    if (!id || id === shownLotId.value) return;

    shownLotId.value = id;
    lotIn.value = false;
    requestAnimationFrame(() => { lotIn.value = true; });
}, { immediate: true });

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

/** Green for sold, red for unsold, amber for skipped — the classic wall's pairing. */
const sealStyle = computed(() => ({
    sold: { ring: '#22c55e', glow: 'rgba(34,197,94,.55)', word: 'SOLD' },
    unsold: { ring: '#ef4444', glow: 'rgba(239,68,68,.55)', word: 'UNSOLD' },
    skipped: { ring: '#f59e0b', glow: 'rgba(245,158,11,.55)', word: 'SKIPPED' },
}[result.value?.outcome] ?? null));

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

const canvasStyle = computed(() => ({
    width: `${cw.value}px`,
    height: `${ch.value}px`,
    transform: `scale(${scale.value})`,
    transformOrigin: 'center center',
    backgroundImage: design.value.background ? `url("${design.value.background}")` : 'none',
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
    stopLocal();
});
</script>

<template>
    <div class="w-screen h-screen overflow-hidden bg-black flex items-center justify-center select-none">
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

        <!-- The template's canvas, at its own pixel size, scaled to fit. -->
        <div v-else class="relative shrink-0" :style="canvasStyle">
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

            <!-- The lot that just settled, stamped. Takes precedence over the stage heading:
                 between lots the result IS the news, and the heading can wait its turn. -->
            <div v-else-if="result && sealStyle" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                <div class="seal-card" :class="{ 'seal-in': sealIn }">
                    <div class="seal-photo" :style="{ borderColor: sealStyle.ring, boxShadow: `0 0 60px ${sealStyle.glow}` }">
                        <img v-if="result.image_path" :src="`/storage/${result.image_path}`" :alt="result.name">
                        <span v-else>{{ (result.name ?? '?').charAt(0) }}</span>
                    </div>

                    <div class="seal-stamp" :style="{ borderColor: sealStyle.ring, color: sealStyle.ring }">
                        {{ sealStyle.word }}
                    </div>

                    <p class="seal-name">{{ result.name }}</p>

                    <div v-if="result.outcome === 'sold'" class="seal-buyer">
                        <img v-if="result.team_logo" :src="result.team_logo" alt="">
                        <span>{{ result.team }}</span>
                    </div>
                    <p v-if="result.price" class="seal-price">{{ money(result.price) }}</p>
                </div>

                <!-- Paper, for a sale only. -->
                <div v-if="confetti.length" class="confetti" aria-hidden="true">
                    <i v-for="c in confetti" :key="c.id"
                       :style="{ left: c.left + '%', background: c.colour,
                                 width: c.size + 'px', height: (c.size * 0.45) + 'px',
                                 animationDelay: c.delay + 's', animationDuration: c.duration + 's',
                                 '--drift': c.drift + 'px', '--spin': c.spin + 'deg' }"></i>
                </div>
            </div>

            <!-- Nobody on the block and nothing just settled: the stage heading the server
                 already computes. -->
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-white">
                <p class="text-6xl font-black stage-in">{{ stage?.heading ?? 'PLEASE WAIT' }}</p>
                <p v-if="stage?.subline" class="mt-4 text-3xl text-white/50 stage-in">{{ stage.subline }}</p>
            </div>

            <!--
                No sold board here, deliberately.

                The template owns the whole canvas: this design fills its bottom band with a
                sponsor strip, and an overlay of recent sales — which is what was here first —
                landed on top of it. A wall that covers a sponsor is worse than a wall without a
                sold list, and the sold board has its own template type and its own screen.
            -->
        </div>
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
.seal-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    opacity: 0;
    transform: scale(.86);
}

.seal-card.seal-in {
    animation: seal-land .55s cubic-bezier(.16, 1, .3, 1) forwards;
}

@keyframes seal-land {
    from { opacity: 0; transform: scale(.86); }
    60%  { opacity: 1; transform: scale(1.03); }
    to   { opacity: 1; transform: scale(1); }
}

.seal-photo {
    width: 18vh; height: 18vh;
    border-radius: 9999px;
    border: .5vh solid;
    overflow: hidden;
    display: grid; place-items: center;
    background: rgba(0, 0, 0, .45);
    font-size: 7vh; font-weight: 900;
}
.seal-photo img { width: 100%; height: 100%; object-fit: cover; }

/* Struck on at an angle, the way a rubber stamp lands. */
.seal-stamp {
    margin-top: -2.5vh;
    padding: .6vh 2.4vh;
    border: .45vh solid;
    border-radius: .8vh;
    font-size: 4.4vh;
    font-weight: 900;
    letter-spacing: .12em;
    background: rgba(0, 0, 0, .72);
    transform: rotate(-8deg);
}

.seal-card.seal-in .seal-stamp { animation: stamp .45s .18s cubic-bezier(.16, 1, .3, 1) backwards; }

@keyframes stamp {
    from { opacity: 0; transform: rotate(-8deg) scale(2.4); }
    to   { opacity: 1; transform: rotate(-8deg) scale(1); }
}

.seal-name { margin-top: 2.4vh; font-size: 5vh; font-weight: 900; }
.seal-buyer { margin-top: 1.2vh; display: flex; align-items: center; gap: 1.2vh; font-size: 3vh; opacity: .85; }
.seal-buyer img { width: 5vh; height: 5vh; border-radius: 9999px; object-fit: cover; }
.seal-price { margin-top: .8vh; font-size: 4.6vh; font-weight: 900; color: #22c55e; }

.stage-in { animation: stage-fade .4s ease both; }
@keyframes stage-fade { from { opacity: 0; transform: translateY(1.5vh); } to { opacity: 1; transform: none; } }

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
    .seal-card.seal-in,
    .seal-card.seal-in .seal-stamp,
    .stage-in { animation: none; opacity: 1; transform: none; }
    .lot-enter.lot-in { animation: none; opacity: 0; }
    .confetti { display: none; }
}
</style>
