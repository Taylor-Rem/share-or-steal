<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import RoomShareRate from './beats/RoomShareRate.vue';
import ShareRateByDecision from './beats/ShareRateByDecision.vue';
import ArchetypeReveal from './beats/ArchetypeReveal.vue';
import ArchetypeCards from './beats/ArchetypeCards.vue';
import ArchetypeCensus from './beats/ArchetypeCensus.vue';
import StatLeaders from './beats/StatLeaders.vue';
import AwardBeat from './beats/AwardBeat.vue';
import PodiumBeat from './beats/PodiumBeat.vue';
import Comparison from './beats/Comparison.vue';

/** One component per beat type (CONTRACT.md § 11.3); the key remounts it so each beat gets its own entrance. */
const store = useGameStore();
const beat = computed(() => store.beat);
const components = {
    room_share_rate: RoomShareRate,
    share_rate_by_decision: ShareRateByDecision,
    archetype_reveal: ArchetypeReveal,
    archetype_cards: ArchetypeCards,
    archetype_census: ArchetypeCensus,
    stat_leaders: StatLeaders,
    award: AwardBeat,
    podium: PodiumBeat,
    comparison: Comparison,
};
</script>

<template>
    <main class="flex h-full flex-col px-16 py-10">
        <header class="flex items-baseline justify-between text-slate-400">
            <p class="text-3xl font-semibold uppercase tracking-[0.3em]">The analysis</p>
            <p v-if="beat" class="font-mono text-2xl">{{ beat.index + 1 }} / {{ beat.count }}</p>
        </header>
        <div class="relative mt-4 flex-1 overflow-hidden">
            <component :is="components[beat.type]" v-if="beat && components[beat.type]" :key="beat.index" :payload="beat.payload" :anonymous="store.state?.mode === 'anonymous'" />
            <div v-else class="flex h-full items-center justify-center text-5xl text-slate-500">{{ beat ? beat.type : 'Computing…' }}</div>
        </div>
    </main>
</template>
