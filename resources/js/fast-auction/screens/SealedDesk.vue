<script setup>
/**
 * The sealed-bid desk.
 *
 * A round moves through a fixed sequence — pending, entry open, collecting, locked, revealed,
 * then awarded, a tie to be drawn, or nobody entering — and at each point only a few things are
 * sensible to do. So the desk renders BY STATE rather than showing every button and disabling
 * most of them: an organizer under pressure should see three choices that all make sense, not
 * fourteen of which eleven are grey.
 *
 * Amounts are never revealed before the round is. `revealed` is the server's word for it and the
 * only thing that opens the figures — the desk does not decide that for itself, because the
 * whole point of a sealed round is that nobody, including the person running it, sees a bid
 * early.
 */
import { computed, ref } from 'vue';
import { post } from '../lib/api';

const props = defineProps({
    sealed: { type: Object, default: null },
    teams: { type: Array, default: () => [] },
    playerId: { type: [Number, String], default: null },
    urls: { type: Object, required: true },
    money: { type: Function, required: true },
    busy: { type: String, default: '' },
});

const emit = defineEmits(['done', 'error']);

const chosen = ref([]);          // team ids for start / open-entry
const manualReason = ref('');
const adjusting = ref({});       // entry id -> typed amount

const round = computed(() => props.sealed ?? null);
const state = computed(() => round.value?.state ?? null);
const entries = computed(() => round.value?.entries ?? []);
const counts = computed(() => round.value?.counts ?? {});
const revealed = computed(() => Boolean(round.value?.revealed));
const tie = computed(() => round.value?.tie ?? null);
const timer = computed(() => round.value?.timer ?? null);

/** Teams still standing, highest first once the round is open. */
const standing = computed(() => entries.value.filter((e) => !e.withdrawn));

const working = ref('');

async function send(key, path, body = {}, confirmText = null) {
    if (working.value || props.busy) return;
    if (confirmText && !window.confirm(confirmText)) return;

    working.value = key;

    try {
        const data = await post(`${props.urls.sealed}/${path}`, {
            auction_player_id: props.playerId,
            ...body,
        });
        emit('done', data.message ?? 'Done.');
    } catch (e) {
        emit('error', e.message);
    } finally {
        working.value = '';
    }
}

/** Per-entry commands live under their own id. */
async function entryCommand(entry, action, body = {}) {
    if (working.value) return;
    working.value = `entry-${entry.entry_id}`;

    try {
        const url = props.urls.sealedEntry
            .replace('__ENTRY__', entry.entry_id)
            .replace(/\/adjust$/, `/${action}`);

        const data = await post(url, { auction_player_id: props.playerId, ...body });
        emit('done', data.message ?? 'Done.');
    } catch (e) {
        emit('error', e.message);
    } finally {
        working.value = '';
    }
}

const toggleTeam = (id) => {
    const at = chosen.value.indexOf(id);
    if (at === -1) chosen.value.push(id);
    else chosen.value.splice(at, 1);
};

const startRound = () => send('start', 'start', chosen.value.length ? { team_ids: chosen.value } : {});
const openEntry = () => send('open', 'open-entry', { team_ids: chosen.value });
const lock = () => send('lock', 'lock', {}, 'Lock the round? No further amounts can be submitted.');
const extend = () => send('extend', 'extend-timer');
const award = () => send('award', 'award', {}, 'Award this player to the leading bid?');
const drawLot = () => send('lot', 'lot', {}, 'Draw a lot between the tied teams?');
const reopen = () => send('reopen', 'reopen-selection', {}, 'Reopen team selection for this round?');
const startRebid = () => send('rebid', 'start-rebid', {}, 'Start a fresh round of sealed bidding?');
const noEntries = (choice) => send(`none-${choice}`, 'no-entries-decision', { choice });

const resolveManual = (entry) => send('manual', 'resolve-manual', {
    team_id: entry.team_id,
    reason: manualReason.value,
}, `Award to ${entry.team_name} by hand?`);

function adjust(entry) {
    const typed = adjusting.value[entry.entry_id];
    if (!typed) return;
    entryCommand(entry, 'adjust', { amount: Number(typed) });
}
</script>

<template>
    <section class="rounded-2xl border border-purple-800/60 bg-purple-950/20 p-4">
        <header class="flex items-center gap-2 mb-3">
            <h2 class="text-sm font-bold text-purple-200">Sealed bidding</h2>

            <span v-if="round" class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-200">
                Round {{ round.round_number }}<span v-if="round.total_rounds">/{{ round.total_rounds }}</span>
                · {{ state }}
            </span>

            <span v-if="timer?.remaining != null" class="ml-auto text-lg font-bold tabular-nums"
                  :class="timer.remaining <= 10 ? 'text-rose-400' : 'text-slate-200'">
                {{ timer.remaining }}s
            </span>
        </header>

        <!-- No round yet: choose who is invited, then start. -->
        <template v-if="!round">
            <p class="text-xs text-slate-400 mb-2">
                Pick the teams to invite, or start with everyone.
            </p>
            <div class="flex flex-wrap gap-1.5 mb-3">
                <button v-for="team in teams" :key="team.id" type="button" @click="toggleTeam(team.id)"
                        class="px-2.5 py-1 rounded-lg border text-xs transition"
                        :class="chosen.includes(team.id)
                            ? 'border-purple-400 bg-purple-500/20 text-purple-100'
                            : 'border-slate-700 bg-slate-900 text-slate-300'">
                    {{ team.name }}
                </button>
            </div>
            <button type="button" @click="startRound" :disabled="!!working || !playerId"
                    class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-sm font-bold disabled:opacity-30">
                Start sealed round<span v-if="chosen.length"> ({{ chosen.length }} teams)</span>
            </button>
        </template>

        <template v-else>
            <!-- Counts, always. This is what the organizer is actually watching. -->
            <div class="flex flex-wrap gap-3 text-xs text-slate-400 mb-3">
                <span>Invited <b class="text-slate-200">{{ counts.invited ?? 0 }}</b></span>
                <span>Submitted <b class="text-emerald-300">{{ counts.submitted ?? 0 }}</b></span>
                <span v-if="counts.withdrawn">Withdrawn <b class="text-rose-300">{{ counts.withdrawn }}</b></span>
                <span v-if="round.floor">Floor <b class="text-slate-200">{{ money(round.floor) }}</b></span>
                <span v-if="round.step">Step <b class="text-slate-200">{{ money(round.step) }}</b></span>
            </div>

            <!-- Entries. Amounts appear only once the SERVER says the round is revealed. -->
            <ul class="space-y-1 mb-3 max-h-56 overflow-y-auto">
                <li v-for="entry in (revealed ? entries : standing)" :key="entry.entry_id"
                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-slate-900/70 text-sm"
                    :class="{ 'opacity-40': entry.withdrawn, 'ring-1 ring-amber-400/60': entry.is_tied }">
                    <img v-if="entry.team_logo" :src="entry.team_logo" alt="" class="w-5 h-5 rounded-full object-cover">
                    <span class="truncate">{{ entry.team_name }}</span>

                    <span v-if="entry.withdrawn" class="text-[10px] uppercase text-rose-300">withdrawn</span>
                    <span v-else-if="entry.submitted" class="text-[10px] uppercase text-emerald-300">in</span>
                    <span v-else class="text-[10px] uppercase text-slate-500">waiting</span>

                    <span class="ml-auto tabular-nums font-semibold">
                        <template v-if="revealed && entry.amount != null">{{ money(entry.amount) }}</template>
                        <template v-else-if="entry.submitted">••••</template>
                    </span>

                    <!-- Corrections, once figures are open. -->
                    <template v-if="revealed && !entry.withdrawn">
                        <input v-model="adjusting[entry.entry_id]" type="number" inputmode="numeric"
                               class="w-24 px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-xs"
                               placeholder="amount">
                        <button type="button" @click="adjust(entry)" :disabled="!!working"
                                class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 border border-slate-600 disabled:opacity-30">Set</button>
                        <button type="button" @click="entryCommand(entry, 'withdraw')" :disabled="!!working"
                                class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 border border-slate-600 text-rose-300 disabled:opacity-30">Withdraw</button>
                    </template>
                    <button v-else-if="entry.withdrawn" type="button" @click="entryCommand(entry, 'reinstate')"
                            :disabled="!!working"
                            class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 border border-slate-600 disabled:opacity-30">Reinstate</button>
                </li>
            </ul>

            <!-- A tie: the draw. -->
            <div v-if="tie" class="mb-3 p-3 rounded-xl border border-amber-600/60 bg-amber-500/10">
                <p class="text-xs text-amber-200">
                    Tied at <b>{{ money(tie.amount) }}</b> — {{ (tie.teams ?? []).map(t => t.name).join(' · ') }}
                </p>
                <button v-if="!tie.drawn_at" type="button" @click="drawLot" :disabled="!!working"
                        class="mt-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-sm font-bold disabled:opacity-30">
                    Draw lot
                </button>
                <p v-else class="mt-2 text-xs text-amber-200/70">Drawing…</p>
            </div>

            <!-- Nobody entered. -->
            <div v-if="state === 'no_entries'" class="mb-3 flex flex-wrap gap-2">
                <p class="w-full text-xs text-slate-400">No entries. What should happen to this player?</p>
                <button type="button" @click="noEntries('unsold')" :disabled="!!working"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">Mark unsold</button>
                <button type="button" @click="noEntries('rebid')" :disabled="!!working"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">Re-bid</button>
            </div>

            <!-- What can sensibly be done next, given where the round is. -->
            <div class="flex flex-wrap gap-2">
                <button v-if="['pending', 'entry_open', 'collecting'].includes(state)" type="button"
                        @click="openEntry" :disabled="!!working || !chosen.length"
                        title="Hand the round to the teams so they enter their own amounts"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    Open entry
                </button>
                <button v-if="['entry_open', 'collecting'].includes(state)" type="button"
                        @click="extend" :disabled="!!working"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    Extend timer
                </button>
                <button v-if="['entry_open', 'collecting'].includes(state)" type="button"
                        @click="lock" :disabled="!!working"
                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-sm font-bold disabled:opacity-30">
                    Lock &amp; reveal
                </button>

                <button v-if="revealed && round.leader" type="button" @click="award" :disabled="!!working"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-sm font-bold disabled:opacity-30">
                    Award to {{ round.leader.team_name }}
                </button>
                <button v-if="revealed" type="button" @click="startRebid" :disabled="!!working"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    Another round
                </button>
                <button type="button" @click="reopen" :disabled="!!working"
                        class="px-3 py-2 rounded-xl bg-slate-800 border border-slate-600 text-sm disabled:opacity-30">
                    Reopen selection
                </button>
            </div>

            <!-- Awarding by hand needs a reason on the record. -->
            <details v-if="revealed" class="mt-3">
                <summary class="text-xs text-slate-400 cursor-pointer">Award by hand</summary>
                <input v-model="manualReason" type="text" placeholder="Reason for the record"
                       class="mt-2 w-full px-2.5 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-xs">
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button v-for="entry in standing" :key="entry.entry_id" type="button"
                            @click="resolveManual(entry)" :disabled="!!working || !manualReason.trim()"
                            class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-600 text-xs disabled:opacity-30">
                        {{ entry.team_name }}
                    </button>
                </div>
            </details>
        </template>
    </section>
</template>
