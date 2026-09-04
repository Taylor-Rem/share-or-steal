<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import Card from './Card.vue';

const store = useGameStore();
const final = computed(() => [...store.cards].reverse().find((c) => c.type === 'podium') ?? store.card);
</script>

<template>
    <main class="flex flex-col items-center gap-6 px-5 py-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">{{ store.ended === 'ended_by_director' ? 'Game ended' : 'Game over' }}</p>
        <Card v-if="final" :card="final" />
        <div v-else class="rounded-3xl bg-slate-900 p-6 text-center">
            <p class="text-lg">Thanks for playing.</p>
            <p class="mt-2 font-mono text-slate-300">{{ store.totalPoints }} points</p>
        </div>
        <ul v-if="store.cards.length > 1" class="flex w-full max-w-sm flex-col gap-2 text-sm">
            <li v-for="c in store.cards.filter((x) => x !== final)" :key="c.index" class="rounded-xl bg-slate-900/70 px-4 py-2 text-slate-300">
                <span class="capitalize">{{ c.type.replace(/_/g, ' ') }}</span>
                <span v-if="c.type === 'award'"> · {{ c.payload.award?.label }}</span>
                <span v-else-if="c.payload?.archetype"> · {{ c.payload.archetype.label }}</span>
            </li>
        </ul>
        <router-link to="/" class="mt-auto rounded-xl bg-slate-800 px-5 py-3 font-semibold">Join another game</router-link>
    </main>
</template>
