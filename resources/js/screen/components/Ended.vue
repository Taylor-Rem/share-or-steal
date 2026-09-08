<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import PodiumBeat from './beats/PodiumBeat.vue';

/** The game is over: the podium stays up if we have it. */
const store = useGameStore();
const podium = computed(() => (store.beat?.type === 'podium' ? store.beat.payload : null));
</script>

<template>
    <main class="flex h-full flex-col px-16 py-10">
        <header class="flex items-baseline justify-between text-slate-400">
            <p class="text-3xl font-semibold uppercase tracking-[0.3em]">{{ store.ended === 'ended_by_director' ? 'Game ended' : 'Thanks for playing' }}</p>
            <p class="font-mono text-2xl">{{ store.code }}</p>
        </header>
        <div class="relative mt-4 flex-1 overflow-hidden">
            <PodiumBeat v-if="podium" :payload="podium" :anonymous="store.state?.mode === 'anonymous'" :still="true" />
            <div v-else class="flex h-full items-center justify-center text-6xl font-black text-slate-300">Game over</div>
        </div>
    </main>
</template>
