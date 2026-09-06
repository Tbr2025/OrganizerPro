<script setup>
/**
 * The offline bid desk — a room bidding out loud, recorded by hand.
 *
 * The classic panel runs an offline lot two ways and so does this. LIVE is the auctioneer's way:
 * tap a team chip, the server's increment ladder decides the raise, and the desk is not involved
 * at all. BATCH is this component: every team in the room writes a figure, the organizer types
 * them in together, and the highest wins. Both end at the same place — `sell-to-team` — so a lot
 * sold from here is indistinguishable from one sold anywhere else.
 *
 * Three phases, and the stepper is not decoration: an organizer who has just typed six amounts
 * needs to see that the round is at "Results" and not still taking bids.
 *
 * Two pieces of state behave differently on purpose, both carried over from the classic desk
 * where they were learned the hard way:
 *
 *  - **Who is in the room persists between players.** It used to reset after every sale, so the
 *    organizer re-ticked the same eight teams for all fourteen players in a pool with the room
 *    waiting. The teams at the table do not change from one player to the next.
 *  - **The amounts do not.** Those are per player, and carrying one over would silently open the
 *    next lot at the last one's price.
 *
 * The selection IS dropped when the pool changes, because a new pool is a different set of teams
 * still needing players — carrying it across would open bid rows for teams nobody chose.
 */
import { computed, ref, watch } from 'vue';
import { toM, fromM } from '../lib/money';

const props = defineProps({
    player: { type: Object, default: null },
    teams: { type: Array, default: () => [] },
    poolId: { type: [Number, String], default: null },
    money: { type: Function, required: true },
    busy: { type: String, default: '' },
});

const emit = defineEmits(['sell']);

const PHASES = [
    { key: 'selection', label: 'Select' },
    { key: 'bidding', label: 'Bids' },
    { key: 'results', label: 'Results' },
];

const phase = ref('selection');
const participants = ref([]);
const bids = ref({});
const dropped = ref('');

const phaseIndex = computed(() => PHASES.findIndex((p) => p.key === phase.value));

/*
 * Only teams that can still act on THIS player.
 *
 * The server already withholds unapproved registrations; what is left to filter is a team with
 * no purse left under the squad-reserve rule, or a squad already full. Offering them in the
 * picker invites a bid the server would refuse — with the room watching.
 */
const selectable = computed(() => props.teams.filter((t) => !t.excluded && !t.squad_full));

const teamById = (id) => props.teams.find((t) => Number(t.id) === Number(id)) ?? null;
const isIn = (id) => participants.value.some((x) => Number(x) === Number(id));

/** Highest first, so the results list reads as a ranking. */
const ranked = computed(() => participants.value
    .map((id) => ({ id: Number(id), amount: Number(bids.value[id]) || 0 }))
    .sort((a, b) => b.amount - a.amount));

const winner = computed(() => (ranked.value[0]?.amount > 0 ? ranked.value[0] : null));

// A new pool means a new room. See the note at the top.
watch(() => props.poolId, () => {
    participants.value = [];
    bids.value = {};
    phase.value = 'selection';
});

// A new player keeps the room and clears the figures.
watch(() => props.player?.id, () => {
    bids.value = {};
    phase.value = 'selection';
    dropped.value = '';
});

function toggle(id) {
    const at = participants.value.findIndex((x) => Number(x) === Number(id));

    if (at === -1) {
        participants.value.push(Number(id));
        return;
    }

    participants.value.splice(at, 1);
    delete bids.value[id];
}

function startBidding() {
    /*
     * Drop anyone who can no longer act before opening the round.
     *
     * The selection is kept across a whole pool, so a team that was in the room for the last
     * player may since have filled its squad or spent down past the squad-reserve ceiling.
     */
    const blocked = new Set(props.teams.filter((t) => t.excluded || t.squad_full).map((t) => Number(t.id)));
    const out = participants.value.filter((id) => blocked.has(Number(id)));

    if (out.length) {
        participants.value = participants.value.filter((id) => !blocked.has(Number(id)));
        out.forEach((id) => delete bids.value[id]);
        dropped.value = `${out.map((id) => teamById(id)?.name ?? 'A team').join(', ')} cannot bid on this player and was left out.`;
    } else {
        dropped.value = '';
    }

    if (!participants.value.length) return;

    // Everyone opens at the base price, so a team that bid nothing is visibly at the floor
    // rather than at zero.
    const base = Number(props.player?.base_price ?? 0);
    participants.value.forEach((id) => {
        if (!bids.value[id]) bids.value[id] = base;
    });

    phase.value = 'bidding';
}

const back = () => { phase.value = phase.value === 'results' ? 'bidding' : 'selection'; };

function sell(id, amount) {
    if (!props.player || !amount) return;
    emit('sell', { teamId: Number(id), amount: Number(amount), teamName: teamById(id)?.name ?? 'the team' });
}

/**
 * Called by the panel once a sale has gone through.
 *
 * The figures go, the room stays.
 */
function settled() {
    bids.value = {};
    phase.value = 'selection';
    dropped.value = '';
}

defineExpose({ settled });
</script>

<template>
    <section class="rounded-2xl border border-orange-800/60 bg-orange-950/20 p-4">
        <header class="flex items-center gap-2 mb-3">
            <h2 class="text-sm font-bold text-orange-200">Offline bidding</h2>
            <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-orange-500/20 text-orange-200">
                by hand
            </span>

            <!-- Where the round is. Not decoration: an organizer who has just typed six amounts
                 needs to see that this is Results and not still taking bids. -->
            <div class="ml-auto flex items-center gap-1">
                <span v-for="(p, i) in PHASES" :key="p.key"
                      class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                      :class="phase === p.key
                          ? 'bg-orange-600 text-white'
                          : (phaseIndex > i ? 'bg-emerald-600/25 text-emerald-300' : 'bg-slate-800 text-slate-500')">
                    {{ phaseIndex > i ? '✓' : (i + 1) }} {{ p.label }}
                </span>
            </div>
        </header>

        <p v-if="dropped" class="mb-3 px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-600/40 text-xs text-amber-200">
            {{ dropped }}
        </p>

        <!-- ── 1. Who is in the room ─────────────────────────────────────── -->
        <div v-if="phase === 'selection'">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 mb-2">
                Teams bidding on {{ player?.name ?? 'this player' }}
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2 mb-3">
                <button v-for="team in selectable" :key="team.id" type="button" @click="toggle(team.id)"
                        class="flex items-center gap-2 p-2.5 rounded-xl border text-left transition"
                        :class="isIn(team.id)
                            ? 'border-orange-500 bg-orange-500/10'
                            : 'border-slate-700 bg-slate-900 hover:bg-slate-800'">
                    <img v-if="team.logo_url" :src="team.logo_url" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                    <div v-else class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold shrink-0">
                        {{ team.short_name || '?' }}
                    </div>

                    <div class="min-w-0">
                        <p class="text-xs font-semibold truncate">{{ team.name }}</p>
                        <p class="text-[10px] text-slate-400">{{ money(team.remaining_budget) }} left</p>
                    </div>

                    <span v-if="isIn(team.id)" class="ml-auto text-orange-400 text-xs">✓</span>
                </button>
            </div>

            <p v-if="!selectable.length" class="text-xs text-slate-500 mb-3">
                No team can bid on this player — every purse is spent down or every squad is full.
            </p>

            <button type="button" @click="startBidding" :disabled="!participants.length"
                    class="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-sm font-bold disabled:opacity-30 disabled:cursor-not-allowed">
                {{ participants.length ? `Start bidding (${participants.length} teams)` : 'Select teams to start' }}
            </button>
        </div>

        <!-- ── 2. The figures ────────────────────────────────────────────── -->
        <div v-else-if="phase === 'bidding'">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 mb-2">Enter each team's bid</p>

            <div class="rounded-xl border border-slate-700 divide-y divide-slate-800 mb-3">
                <div v-for="id in participants" :key="id" class="flex items-center gap-3 p-2.5">
                    <img v-if="teamById(id)?.logo_url" :src="teamById(id).logo_url" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                    <div v-else class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-bold shrink-0">
                        {{ teamById(id)?.short_name || '?' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold truncate">{{ teamById(id)?.name }}</p>
                        <p class="text-[10px] text-slate-400">{{ money(teamById(id)?.remaining_budget) }} left</p>
                    </div>

                    <!--
                        MILLIONS, and labelled M.

                        Everything on these screens reads on the K/M/B ladder, so the figure typed
                        has to be on the same one. The classic panel carried this input labelled
                        "L" for lakhs once: an organizer typing 45 meaning 45 lakh sold the player
                        for 45M instead, a ten-times error entered on their behalf mid-auction.

                        step="any" because the server validates against the increment ladder; a
                        hardcoded step makes the browser refuse ordinary figures like 4.7.
                    -->
                    <div class="flex items-center rounded-lg border border-slate-600 bg-slate-900 focus-within:border-orange-500">
                        <input type="number" min="0" step="any" placeholder="0"
                               :value="toM(bids[id])"
                               @input="bids[id] = fromM($event.target.value)"
                               class="w-24 bg-transparent px-2 py-1.5 text-right text-sm outline-none">
                        <span class="pr-2 text-[10px] text-slate-500">M</span>
                    </div>

                    <button type="button" @click="toggle(id)" class="px-2 text-slate-500 hover:text-red-400" title="Take out of the room">✕</button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="back" class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm">← Teams</button>
                <button type="button" @click="phase = 'results'"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-sm font-bold">
                    End bidding &amp; show winner
                </button>
            </div>
        </div>

        <!-- ── 3. Who won ────────────────────────────────────────────────── -->
        <div v-else>
            <div v-if="winner" class="rounded-xl border-2 border-emerald-500 bg-emerald-500/10 p-4 text-center mb-3">
                <p class="text-[10px] uppercase tracking-widest text-emerald-300 mb-1">Highest bid</p>
                <p class="text-lg font-bold">{{ teamById(winner.id)?.name }}</p>
                <p class="text-3xl font-black text-emerald-300 tabular-nums my-1">{{ money(winner.amount) }}</p>

                <button type="button" @click="sell(winner.id, winner.amount)" :disabled="!!busy"
                        class="mt-1 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold text-sm disabled:opacity-30">
                    Sell to {{ teamById(winner.id)?.short_name || teamById(winner.id)?.name }}
                </button>
            </div>

            <p v-else class="mb-3 px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-400">
                Nobody bid above zero. Go back and enter the figures, or pass the player.
            </p>

            <!-- Every figure, so a mistyped winner can be corrected without starting again —
                 the organizer sells to whichever row is actually right. -->
            <div class="rounded-xl border border-slate-700 divide-y divide-slate-800 mb-3">
                <div v-for="row in ranked" :key="row.id" class="flex items-center gap-3 p-2.5"
                     :class="row.id === winner?.id ? 'bg-emerald-500/5' : ''">
                    <span class="text-xs text-slate-500 w-4">{{ ranked.indexOf(row) + 1 }}</span>
                    <p class="text-xs font-semibold flex-1 truncate">{{ teamById(row.id)?.name }}</p>
                    <p class="text-sm font-bold tabular-nums"
                       :class="row.id === winner?.id ? 'text-emerald-300' : 'text-slate-400'">{{ money(row.amount) }}</p>

                    <button v-if="row.id !== winner?.id && row.amount > 0" type="button"
                            @click="sell(row.id, row.amount)" :disabled="!!busy"
                            class="px-2 py-1 rounded-lg bg-slate-800 border border-slate-600 text-[10px] disabled:opacity-30">
                        Sell here
                    </button>
                </div>
            </div>

            <button type="button" @click="back" class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm">← Bids</button>
        </div>
    </section>
</template>
