<script setup>
import { useGameStore } from '../../shared/stores/game';

const store = useGameStore();
</script>

<template>
    <main class="flex flex-col items-center justify-center gap-4 px-6 py-8 text-center">
        <div class="text-5xl" aria-hidden="true">⏳</div>
        <h1 class="text-2xl font-black">Game in progress</h1>
        <p class="text-slate-300">You're on the list{{ store.me ? ` as ${store.me.username}` : '' }}. Watch the big screen; the director can let you in at the next round.</p>
        <p class="text-xs text-slate-500">Keep this page open. It updates on its own.</p>
    </main>
</template>
