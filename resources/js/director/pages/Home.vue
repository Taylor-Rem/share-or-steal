<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { recall, remember } from '../../shared/device';

// Session 4 builds create-session, mode toggle and history. This only stores the key and routes.
const router = useRouter();
const key = ref(recall('director_key') ?? '');
const code = ref('');

function go() {
    remember('director_key', key.value);
    if (code.value.trim()) router.push(`/director/${code.value.trim().toUpperCase()}`);
}
</script>

<template>
    <main class="flex min-h-screen flex-col items-center justify-center gap-4 p-6 font-mono">
        <p class="rounded border border-amber-500/60 bg-amber-500/10 p-3 text-sm text-amber-200">
            Director — Session 0 placeholder. Not the control panel.
        </p>
        <form class="flex flex-col gap-2" @submit.prevent="go">
            <input v-model="key" type="password" placeholder="director password" class="rounded bg-slate-800 p-3" />
            <input v-model="code" maxlength="4" placeholder="CODE" class="rounded bg-slate-800 p-3 text-center uppercase tracking-widest" />
            <button class="rounded bg-green-600 p-3">Open session</button>
        </form>
    </main>
</template>
