<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';

/** The ten decision slots of the round, filling in as reveals arrive. */
const props = defineProps({ current: { type: Number, default: null } });
const store = useGameStore();

const slots = computed(() =>
    Array.from({ length: store.decisionsPerRound }, (_, i) => {
        const index = i + 1;
        const reveal = store.reveals[index];
        return { index, reveal, current: index === props.current && !reveal };
    }),
);

const classes = {
    mutual_share: 'bg-emerald-500 text-slate-950',
    betrayer: 'bg-amber-400 text-slate-950',
    betrayed: 'bg-rose-500 text-white',
    mutual_steal: 'bg-slate-500 text-white',
};
</script>

<template>
    <ol class="flex justify-center gap-1.5" aria-label="Decisions this round">
        <li
            v-for="s in slots"
            :key="s.index"
            class="flex h-8 w-7 items-center justify-center rounded-md text-xs font-bold transition-colors motion-reduce:transition-none"
            :class="s.reveal ? classes[s.reveal.outcome] : s.current ? 'sos-pulse border-2 border-slate-300' : 'border border-slate-700 text-slate-600'"
            :aria-label="s.reveal ? `Decision ${s.index}: ${s.reveal.outcome.replace('_', ' ')}, ${s.reveal.you.points} points` : `Decision ${s.index}`"
        >
            <template v-if="s.reveal">+{{ s.reveal.you.points }}</template>
            <template v-else>{{ s.index }}</template>
        </li>
    </ol>
</template>
