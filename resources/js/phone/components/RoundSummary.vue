<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../composables/useCountdown';
import PartnerName from './PartnerName.vue';
import Track from './Track.vue';

const store = useGameStore();
const summary = computed(() => store.roundSummary);
const deadline = computed(() => store.state?.phase_ends_at);
const { seconds } = useCountdown(store.clock, deadline);
const ordinal = (n) => `${n}${['th', 'st', 'nd', 'rd'][(n % 100 > 10 && n % 100 < 14) || n % 10 > 3 ? 0 : n % 10]}`;
</script>

<template>
    <main class="flex flex-col items-center gap-6 px-5 py-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Round {{ summary?.round ?? store.round?.number }} done</p>
        <Track />
        <template v-if="summary">
            <div class="sos-rise flex flex-col items-center">
                <span class="font-mono text-6xl font-black tabular-nums">+{{ summary.round_points }}</span>
                <span class="text-slate-400">this round · {{ summary.total_points }} total</span>
            </div>
            <div class="sos-rise grid w-full max-w-sm grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-slate-900 p-3"><div class="text-2xl font-bold">{{ ordinal(summary.rank) }}</div><div class="text-xs text-slate-400">of {{ summary.player_count }}</div></div>
                <div class="rounded-2xl bg-slate-900 p-3"><div class="text-2xl font-bold text-emerald-300">{{ summary.shares }}</div><div class="text-xs text-slate-400">shares</div></div>
                <div class="rounded-2xl bg-slate-900 p-3"><div class="text-2xl font-bold text-rose-300">{{ summary.steals }}</div><div class="text-xs text-slate-400">steals</div></div>
            </div>
            <p class="text-center text-sm text-slate-300">
                <PartnerName :partner="summary.partner" /> stole from you {{ summary.stolen_from }} {{ summary.stolen_from === 1 ? 'time' : 'times' }}.
            </p>
        </template>
        <p v-else class="text-slate-400">Adding it up…</p>
        <p class="mt-auto text-sm text-slate-400">
            <template v-if="summary?.is_last">The analysis is coming up. Watch the screen.</template>
            <template v-else>New partner in <span class="font-mono font-bold text-white">{{ seconds }}</span></template>
        </p>
    </main>
</template>
