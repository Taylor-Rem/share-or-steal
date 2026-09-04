<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../../shared/useCountdown';
import { describeState } from '../logic';

const store = useGameStore();
const deadline = computed(() => (store.state?.paused ? null : store.state?.phase_ends_at));
const { seconds } = useCountdown(store.clock, deadline);
const tone = computed(() => {
    if (store.state?.paused) return 'text-amber-300';
    return { lobby: 'text-slate-200', deciding: 'text-emerald-300', revealing: 'text-sky-300', analysis: 'text-violet-300', finished: 'text-slate-400' }[store.state?.status] ?? 'text-slate-200';
});
</script>

<template>
    <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-900 px-4 py-3">
        <div class="min-w-0">
            <div class="truncate text-lg font-bold" :class="tone">{{ describeState(store.state) }}</div>
            <div class="text-xs text-slate-400">
                {{ store.state?.player_count ?? 0 }} players · {{ store.state?.mode }} · {{ store.state?.rounds_count }}×{{ store.state?.decisions_per_round }}{{ store.state?.fast_mode ? ' · fast' : '' }}
            </div>
        </div>
        <div v-if="deadline" class="font-mono text-3xl font-bold tabular-nums" :class="tone">{{ seconds }}</div>
        <div v-else-if="store.state?.paused" class="text-2xl" aria-label="Paused">⏸</div>
    </div>
</template>
