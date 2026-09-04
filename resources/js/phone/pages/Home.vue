<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { recall } from '../../shared/device';

// Session 2 builds the real join flow (code, username, remembers you). This just routes to /play/{code}.
const router = useRouter();
const code = ref(recall('last_code') ?? '');

function go() {
    if (code.value.trim()) router.push(`/play/${code.value.trim().toUpperCase()}`);
}
</script>

<template>
    <main class="flex min-h-screen flex-col items-center justify-center gap-4 p-6 font-mono">
        <p class="rounded border border-amber-500/60 bg-amber-500/10 p-3 text-sm text-amber-200">
            Phone — Session 0 placeholder. Not the game UI.
        </p>
        <form class="flex gap-2" @submit.prevent="go">
            <input
                v-model="code"
                maxlength="4"
                placeholder="CODE"
                class="w-32 rounded bg-slate-800 p-3 text-center text-2xl uppercase tracking-widest"
            />
            <button class="rounded bg-green-600 px-4 text-lg">Go</button>
        </form>
    </main>
</template>
