<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { get } from '../lib/api';
import { connect } from '../lib/realtime';
import { moneyFor, priceLabel } from '../lib/money';
import { customImages, elementStyle, isVisible, tableColumns } from '../lib/design';

const props = defineProps({
    boot: { type: Object, required: true },
});

const snap = ref(props.boot.snapshot ?? {});
const design = props.boot.design ?? {};
const positions = design.positions ?? {};

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
const cw = Number(design.canvasWidth || 1601);
const ch = Number(design.canvasHeight || 910);
const scale = ref(1);

function fit() {
    scale.value = Math.min(window.innerWidth / cw, window.innerHeight / ch);
}

const canvasStyle = computed(() => ({
    width: `${cw}px`,
    height: `${ch}px`,
    transform: `scale(${scale.value})`,
    transformOrigin: 'center center',
    backgroundImage: design.background ? `url("${design.background}")` : 'none',
    backgroundSize: 'cover',
    backgroundPosition: 'center',
}));

/** Position + style straight from the template. */
const at = (key, fallback = {}) => elementStyle(positions, key, fallback);
const shown = (key) => isVisible(positions, key);

const money = moneyFor(props.boot.amountUnit);

const columns = tableColumns(positions);
const images = customImages(positions);

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

onMounted(() => {
    fit();
    window.addEventListener('resize', fit);

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

onUnmounted(() => window.removeEventListener('resize', fit));
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
            <!-- Custom artwork the organizer placed, underneath everything by z-index. -->
            <img v-for="img in images" :key="img.key"
                 :src="`/storage/${img.path}`" alt=""
                 :style="at(img.key)">

            <template v-if="onBlock">
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

            <!-- Nobody on the block: the stage heading the server already computes. -->
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-white">
                <p class="text-6xl font-black">{{ stage?.heading ?? 'PLEASE WAIT' }}</p>
                <p v-if="stage?.subline" class="mt-4 text-3xl text-white/50">{{ stage.subline }}</p>
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
