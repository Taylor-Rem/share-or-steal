<script setup>
import { ref } from 'vue';
import { useDirector } from '../useDirector';

const director = useDirector();
const password = ref('');
const error = ref(null);
const busy = ref(false);

async function submit() {
    if (!password.value || busy.value) return;
    busy.value = true;
    error.value = null;
    try {
        await director.login(password.value);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-dvh flex-col items-center justify-center gap-6 px-6">
        <header class="text-center">
            <h1 class="text-3xl font-black">Director</h1>
            <p class="mt-1 text-slate-400">Share or Steal control panel</p>
        </header>
        <form class="flex w-full max-w-xs flex-col gap-3" @submit.prevent="submit">
            <input
                v-model="password"
                type="password"
                autocomplete="current-password"
                placeholder="Director password"
                class="h-14 rounded-2xl border border-slate-700 bg-slate-900 px-4 text-center text-lg text-white placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none"
            />
            <p v-if="error" class="rounded-xl border border-rose-500/50 bg-rose-500/10 px-4 py-3 text-center text-sm text-rose-200" role="alert">{{ error }}</p>
            <button type="submit" :disabled="!password || busy" class="h-14 touch-manipulation rounded-2xl bg-emerald-500 text-lg font-bold text-slate-950 active:scale-95 disabled:opacity-40">
                {{ busy ? 'Checking…' : 'Unlock' }}
            </button>
        </form>
    </main>
</template>
