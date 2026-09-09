<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import SoundToggle from './SoundToggle.vue';
import Avatar from '../../shared/Avatar.vue';

const store = useGameStore();
const reconnecting = computed(() => !store.isFixture && store.connection !== 'connected' && store.connection !== 'idle');
const where = computed(() => {
    const s = store.state;
    if (!s?.round) return null;
    if (s.status === 'analysis') return 'Analysis';
    if (s.status === 'finished') return 'Final';
    return `Round ${s.round} of ${s.rounds_count}`;
});
</script>

<template>
    <header class="flex h-12 items-center justify-between px-4 text-sm text-slate-400">
        <div class="flex items-center gap-2 font-mono">
            <span class="font-semibold tracking-widest text-slate-200">{{ store.code ?? '' }}</span>
            <span v-if="where">· {{ where }}</span>
        </div>
        <div class="flex items-center gap-3">
            <span v-if="reconnecting" class="sos-pulse rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300">reconnecting…</span>
            <span v-if="store.isFixture" class="rounded-full bg-violet-500/20 px-2 py-0.5 text-xs text-violet-300">fixture</span>
            <span v-if="store.me" class="flex items-center gap-1.5"><Avatar :avatar="store.me.avatar" :name="store.me.username" :size="1.6" /><span class="max-w-28 truncate">{{ store.me.username }}</span></span>
            <SoundToggle />
        </div>
    </header>
</template>
