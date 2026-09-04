<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import Card from './Card.vue';

const store = useGameStore();
const beatLabel = computed(() => (store.beat?.type ?? store.state?.status ?? '').replace(/_/g, ' '));
</script>

<template>
    <main class="flex flex-col items-center gap-6 px-5 py-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Analysis</p>
        <Card v-if="store.card" :card="store.card" />
        <div v-else class="flex flex-col items-center gap-3 py-10 text-center">
            <div class="text-5xl" aria-hidden="true">📺</div>
            <p class="text-lg">Watch the big screen.</p>
            <p class="text-sm text-slate-400">Your card lands here when it's your turn.</p>
        </div>
        <p class="mt-auto text-xs text-slate-500">
            <span v-if="store.beat">{{ beatLabel }} · beat {{ store.beat.index + 1 }} of {{ store.beat.count }}</span>
            <span v-if="store.cards.length > 1"> · {{ store.cards.length }} cards so far</span>
        </p>
    </main>
</template>
