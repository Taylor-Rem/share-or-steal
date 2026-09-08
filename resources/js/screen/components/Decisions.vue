<script setup>
import { computed, ref, watch } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../../shared/useCountdown';
import { useAudio } from '../../shared/audio';
import { pct } from '../logic';
import Leaderboard from './Leaderboard.vue';
import Feed from './Feed.vue';

/** deciding + revealing: never a live choice. Round, decision, countdown bar, board, feed. */
defineProps({ feed: { type: Array, default: () => [] } });
const store = useGameStore();
const audio = useAudio();
const anonymous = computed(() => store.state?.mode === 'anonymous');

// The bar drains over the phase; remember when the phase started to know its length.
const deadline = computed(() => store.state?.phase_ends_at);
const total = ref(1);
watch(
    deadline,
    (d) => {
        if (d) total.value = Math.max(1, Date.parse(d) - store.clock.now());
    },
    { immediate: true },
);
const { fraction, seconds } = useCountdown(store.clock, deadline, total);
const deciding = computed(() => store.status === 'deciding');
watch(seconds, (s, was) => {
    if (deciding.value && s !== was && s > 0 && s <= 3) audio.cue('tick');
});

const board = ref([]);
watch(
    () => store.publicReveal,
    (r) => {
        if (r?.leaderboard?.length) board.value = r.leaderboard;
    },
    { immediate: true },
);
const aggregate = computed(() => store.publicReveal?.aggregate ?? null);
</script>

<template>
    <main class="flex h-full flex-col px-16 py-10">
        <header class="flex items-end justify-between">
            <div>
                <p class="text-3xl font-semibold uppercase tracking-[0.3em] text-slate-400">Round {{ store.state?.round }} of {{ store.state?.rounds_count }}</p>
                <h1 class="text-7xl font-black">Decision {{ store.state?.decision }} <span class="text-slate-500">of {{ store.state?.decisions_per_round }}</span></h1>
            </div>
            <p class="text-5xl font-bold" :class="deciding ? 'text-emerald-300' : 'text-sky-300'">{{ deciding ? 'Choose…' : 'Revealed' }}</p>
        </header>

        <div class="mt-6 h-4 w-full overflow-hidden rounded-full bg-slate-800" role="progressbar" :aria-valuenow="Math.round(fraction * 100)">
            <div class="h-full rounded-full" :class="deciding ? 'bg-emerald-400' : 'bg-sky-400'" :style="{ width: `${fraction * 100}%` }" />
        </div>

        <div class="mt-8 grid flex-1 grid-cols-[1.2fr_1fr] gap-12 overflow-hidden">
            <section v-if="anonymous" class="flex flex-col justify-center gap-6 rounded-3xl bg-slate-900 p-12">
                <p class="text-3xl uppercase tracking-widest text-slate-400">The room this decision</p>
                <template v-if="aggregate">
                    <p class="text-[9rem] font-black leading-none" :class="(aggregate.share_rate ?? 0) >= 0.5 ? 'text-emerald-300' : 'text-rose-300'">{{ pct(aggregate.share_rate) }}</p>
                    <p class="text-4xl text-slate-300">shared · {{ aggregate.mutual_share }} pairs both shared · {{ aggregate.betrayals }} betrayals · {{ aggregate.mutual_steal }} both stole</p>
                </template>
                <p v-else class="text-5xl text-slate-500">Waiting for the first reveal…</p>
            </section>
            <section v-else class="flex min-h-0 flex-col">
                <h2 class="mb-3 text-2xl uppercase tracking-widest text-slate-400">Leaderboard</h2>
                <p v-if="!board.length" class="rounded-2xl bg-slate-900 px-6 py-10 text-center text-3xl text-slate-500">First reveal coming up</p>
                <Leaderboard v-else :entries="board" :limit="10" />
            </section>

            <section class="flex min-h-0 flex-col">
                <h2 class="mb-3 text-2xl uppercase tracking-widest text-slate-400">{{ anonymous ? 'Room' : 'Moments' }}</h2>
                <div v-if="anonymous" class="rounded-3xl bg-slate-900 p-10 text-3xl text-slate-300">
                    <p>{{ store.state?.player_count }} players, {{ store.state?.decisions_per_round }} decisions a round.</p>
                    <p class="mt-4 text-slate-500">Every result is private. Watch your phone.</p>
                </div>
                <Feed v-else :items="feed" />
            </section>
        </div>
    </main>
</template>
