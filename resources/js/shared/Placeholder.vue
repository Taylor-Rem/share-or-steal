<script setup>
import { computed } from 'vue';
import { useGameStore } from './stores/game';

/**
 * Session 0 placeholder. Shows the raw state and events a client receives so we can
 * prove a broadcast reaches it. Every later session replaces this with real UI.
 */
const props = defineProps({
    title: { type: String, required: true },
});

const store = useGameStore();
const stateJson = computed(() => JSON.stringify(store.state, null, 2));
</script>

<template>
    <main class="min-h-screen p-4 font-mono text-sm">
        <header class="mb-4 rounded border border-amber-500/60 bg-amber-500/10 p-3 text-amber-200">
            <strong>{{ props.title }}</strong> — Session 0 placeholder. This is not the game UI.
        </header>

        <section class="mb-4 grid gap-1">
            <div>code: <b>{{ store.code ?? '—' }}</b></div>
            <div>
                connection:
                <b :class="store.connection === 'connected' ? 'text-green-400' : 'text-amber-300'">{{ store.connection }}</b>
            </div>
            <div>channels: {{ store.channels.join(', ') || '—' }}</div>
            <div>clock offset: {{ store.clock.offset() === null ? '—' : Math.round(store.clock.offset()) + ' ms' }}</div>
            <div v-if="store.lastError" class="text-red-400">error: {{ store.lastError }}</div>
        </section>

        <slot />

        <section class="mb-4">
            <h2 class="mb-1 text-slate-400">state</h2>
            <pre class="overflow-x-auto rounded bg-slate-900 p-3">{{ stateJson }}</pre>
        </section>

        <section v-if="store.players.length" class="mb-4">
            <h2 class="mb-1 text-slate-400">players</h2>
            <ul class="flex flex-wrap gap-2">
                <li v-for="p in store.players" :key="p.id" class="rounded bg-slate-800 px-2 py-1">
                    {{ p.username }}<span v-if="p.is_bot" class="text-slate-500"> (bot)</span>
                </li>
            </ul>
        </section>

        <section>
            <h2 class="mb-1 text-slate-400">events ({{ store.events.length }})</h2>
            <p v-if="!store.events.length" class="text-slate-500">
                Waiting. Run <code>php artisan game:ping {{ store.code }}</code> to send one.
            </p>
            <details v-for="(e, i) in store.events" :key="i" class="mb-1 rounded bg-slate-900 p-2" :open="i === 0">
                <summary>
                    <span class="text-green-300">{{ e.event }}</span>
                    <span class="text-slate-500"> on {{ e.channel }} at {{ e.at.slice(11, 23) }}</span>
                </summary>
                <pre class="mt-2 overflow-x-auto text-xs">{{ JSON.stringify(e.payload, null, 2) }}</pre>
            </details>
        </section>
    </main>
</template>
