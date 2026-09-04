<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';

const store = useGameStore();
const count = computed(() => store.state?.player_count ?? store.players.length);
const anonymous = computed(() => store.state?.mode === 'anonymous');
</script>

<template>
    <main class="flex flex-col items-center gap-6 px-6 py-8 text-center">
        <div class="sos-pop flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500/15 text-5xl">✓</div>
        <div>
            <h1 class="text-3xl font-black">You're in{{ store.me ? `, ${store.me.username}` : '' }}</h1>
            <p class="mt-2 text-slate-400">{{ count }} {{ count === 1 ? 'player' : 'players' }} in the room. Waiting for the director to start.</p>
        </div>
        <p v-if="anonymous" class="rounded-xl bg-violet-500/15 px-4 py-3 text-sm text-violet-200">This is an anonymous game. Partners appear as codenames and the screen shows no names.</p>
        <ul v-else-if="store.players.length" class="flex max-w-sm flex-wrap justify-center gap-2">
            <li v-for="p in store.players" :key="p.id" class="sos-pop rounded-full bg-slate-800 px-3 py-1.5 text-sm" :class="p.id === store.me?.id && 'ring-2 ring-emerald-400'">
                {{ p.username }}
            </li>
        </ul>
        <p class="mt-auto text-xs text-slate-500">Keep an eye on the big screen.</p>
    </main>
</template>
