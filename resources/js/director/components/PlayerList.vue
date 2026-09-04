<script setup>
import { computed, onScopeDispose, ref } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { age, sortPlayers } from '../logic';
import ConfirmButton from './ConfirmButton.vue';

const props = defineProps({ busyId: { type: Number, default: null }, nudgeAt: { type: Number, default: 3 } });
const emit = defineEmits(['admit', 'kick']);
const store = useGameStore();

const now = ref(Date.now());
const timer = setInterval(() => (now.value = Date.now()), 1000);
onScopeDispose(() => clearInterval(timer));

const rows = computed(() => sortPlayers(Object.values(store.directorPlayers)));
</script>

<template>
    <section>
        <h2 class="mb-2 flex items-baseline justify-between text-sm font-semibold uppercase tracking-wider text-slate-400">
            <span>Players</span>
            <span class="font-mono text-xs">{{ rows.filter((p) => !p.is_bot && !p.kicked).length }}</span>
        </h2>
        <p v-if="!rows.length" class="rounded-xl bg-slate-900 px-4 py-6 text-center text-slate-500">Nobody yet. The join URL is on the screen.</p>
        <ul v-else class="flex flex-col gap-1.5">
            <li
                v-for="p in rows"
                :key="p.id"
                class="flex items-center gap-3 rounded-xl bg-slate-900 px-3 py-2"
                :class="{ 'opacity-50': p.kicked, 'ring-1 ring-amber-400/60': !p.is_admitted && !p.kicked }"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate font-semibold">{{ p.username }}</span>
                        <span v-if="p.is_bot" class="rounded bg-slate-700 px-1 text-[10px] font-bold uppercase text-slate-300">bot</span>
                        <span v-else-if="p.kicked" class="rounded bg-rose-500/20 px-1 text-[10px] font-bold uppercase text-rose-300">kicked</span>
                        <span v-else-if="!p.is_admitted" class="rounded bg-amber-400/20 px-1 text-[10px] font-bold uppercase text-amber-300">waiting</span>
                    </div>
                    <div class="flex gap-3 text-xs text-slate-400">
                        <span v-if="!p.is_bot">{{ age(p.last_seen_at, now) }}</span>
                        <span v-if="p.consecutive_timeouts" :class="p.consecutive_timeouts >= nudgeAt ? 'font-bold text-amber-300' : ''">{{ p.consecutive_timeouts }} silent</span>
                    </div>
                </div>
                <span class="font-mono text-lg tabular-nums">{{ p.total_points }}</span>
                <ConfirmButton v-if="!p.is_admitted && !p.kicked" label="Admit" tone="primary" :busy="busyId === p.id" @confirmed="emit('admit', p)" />
                <ConfirmButton v-else-if="!p.kicked && !p.is_bot" label="Kick" tone="neutral" confirm :busy="busyId === p.id" @confirmed="emit('kick', p)" />
            </li>
        </ul>
    </section>
</template>
