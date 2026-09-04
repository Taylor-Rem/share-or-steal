<script setup>
import { useGameStore } from '../../shared/stores/game';

const store = useGameStore();
</script>

<template>
    <main class="flex flex-col items-center justify-center gap-4 px-6 py-8 text-center">
        <div class="text-5xl" aria-hidden="true">👋</div>
        <h1 class="text-2xl font-black">You're in</h1>
        <p class="text-slate-300">Round {{ store.state?.round }} is under way. You'll get a partner when the next round starts.</p>
    </main>
</template>
