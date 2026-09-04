<script setup>
import { useGameStore } from '../../shared/stores/game';

const store = useGameStore();
</script>

<template>
    <div class="sos-rise fixed inset-x-4 top-14 z-30 rounded-2xl border border-amber-400/60 bg-amber-500/20 px-4 py-3 text-center text-amber-100 backdrop-blur" role="status">
        <p class="text-lg font-bold">Still there?</p>
        <p class="text-sm">{{ store.nudge }} decisions in a row with no tap. Silence counts as Share.</p>
    </div>
</template>
