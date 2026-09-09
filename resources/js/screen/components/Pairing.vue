<script setup>
import { computed, onMounted, ref } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../../shared/useCountdown';
import { from, dur } from '../anim';
import { clip } from '../logic';

const store = useGameStore();
const anonymous = computed(() => store.state?.mode === 'anonymous');
const deadline = computed(() => store.state?.phase_ends_at);
const { seconds } = useCountdown(store.clock, deadline);
const grid = ref(null);

onMounted(() => {
    if (!grid.value) return;
    from(grid.value.children, { y: 40, opacity: 0, duration: dur(0.6), stagger: dur(0.12), ease: 'power3.out' });
});
</script>

<template>
    <main class="flex h-full flex-col px-20 py-12">
        <header class="flex items-baseline justify-between">
            <h1 class="text-6xl font-black">Round {{ store.state?.round }} <span class="text-slate-500">of {{ store.state?.rounds_count }}</span></h1>
            <p class="text-4xl text-slate-400">First decision in <span class="font-mono font-bold text-white">{{ seconds }}</span></p>
        </header>

        <div v-if="anonymous" class="flex flex-1 items-center justify-center">
            <div class="sos-pop rounded-[3rem] bg-slate-900 px-24 py-16 text-center">
                <p class="text-9xl font-black">{{ store.state?.player_count }}</p>
                <p class="mt-2 text-4xl text-slate-400">players paired · codenames on phones</p>
            </div>
        </div>
        <div v-else ref="grid" class="mt-10 grid flex-1 content-start gap-4" :class="store.pairs.length > 8 ? 'grid-cols-3' : 'grid-cols-2'">
            <div v-for="(pair, i) in store.pairs" :key="i" class="flex items-center justify-between gap-4 rounded-3xl bg-slate-900 px-8 py-5" :class="store.pairs.length > 8 ? 'text-3xl' : 'text-4xl'">
                <span class="min-w-0 flex-1 truncate font-bold" :title="pair.a.username">{{ clip(pair.a.username, 16) }}<span v-if="pair.a.is_bot" class="ml-2 rounded bg-slate-700 px-2 text-base uppercase text-slate-300">bot</span></span>
                <span class="text-slate-500">vs</span>
                <span class="min-w-0 flex-1 truncate text-right font-bold" :title="pair.b.username">{{ clip(pair.b.username, 16) }}<span v-if="pair.b.is_bot" class="ml-2 rounded bg-slate-700 px-2 text-base uppercase text-slate-300">bot</span></span>
            </div>
        </div>
    </main>
</template>
