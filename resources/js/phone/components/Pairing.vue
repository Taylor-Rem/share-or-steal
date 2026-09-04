<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../composables/useCountdown';
import PartnerName from './PartnerName.vue';
import Track from './Track.vue';

const store = useGameStore();
const deadline = computed(() => store.state?.phase_ends_at);
const { seconds } = useCountdown(store.clock, deadline);
</script>

<template>
    <main class="flex flex-col items-center justify-center gap-8 px-6 py-8 text-center">
        <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Round {{ store.round?.number }}</p>
        <div class="sos-rise">
            <p class="text-lg text-slate-300">You're playing against</p>
            <p class="mt-1 text-4xl font-black"><PartnerName /></p>
            <p v-if="store.partner?.is_bot" class="mt-2 text-sm text-slate-400">The Machine shares first, then copies your last move.</p>
        </div>
        <Track />
        <p class="text-slate-400">{{ store.decisionsPerRound }} decisions. First one in <span class="font-mono font-bold text-white">{{ seconds }}</span>…</p>
    </main>
</template>
