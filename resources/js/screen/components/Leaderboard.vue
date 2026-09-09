<script setup>
import { computed } from 'vue';
import { clip, movementArrow } from '../logic';
import Avatar from '../../shared/Avatar.vue';

/**
 * A ranked list with movement arrows. Rows are positioned by rank and transition to their
 * new place when the order changes, so a reorder is a slide, not a redraw. Pure CSS: it
 * needs no measuring and keeps working in a background tab.
 */
const props = defineProps({
    entries: { type: Array, required: true },
    limit: { type: Number, default: 10 },
    size: { type: String, default: 'md' },
});
const rowRem = computed(() => (props.size === 'lg' ? 4.6 : 3.9));
const rows = computed(() => props.entries.slice(0, props.limit).map((e, i) => ({ ...e, top: `${i * rowRem.value}rem` })));
</script>

<template>
    <ol class="relative" :style="{ height: `${rows.length * rowRem}rem` }">
        <li
            v-for="e in rows"
            :key="e.player.id"
            class="sos-rise absolute inset-x-0 flex items-center gap-4 rounded-2xl bg-slate-900 px-5 transition-[top] duration-700 ease-in-out motion-reduce:transition-none"
            :class="[size === 'lg' ? 'py-3 text-3xl' : 'py-2 text-2xl', e.rank === 1 && 'ring-2 ring-amber-400/70']"
            :style="{ top: e.top }"
        >
            <span class="w-10 text-right font-mono text-slate-500">{{ e.rank }}</span>
            <Avatar :avatar="e.player.avatar" :name="e.player.username" :size="size === 'lg' ? 2.6 : 2.2" />
            <span class="min-w-0 flex-1 truncate font-semibold" :title="e.player.username">{{ clip(e.player.username, 16) }}<span v-if="e.player.is_bot" class="ml-2 rounded bg-slate-700 px-2 text-sm uppercase text-slate-300">bot</span></span>
            <span class="w-14 text-right font-mono text-lg" :class="e.movement > 0 ? 'text-emerald-300' : e.movement < 0 ? 'text-rose-300' : 'text-slate-600'">{{ movementArrow(e.movement) }}</span>
            <span class="w-20 text-right font-mono text-slate-400">+{{ e.round_points }}</span>
            <span class="w-24 text-right font-mono font-bold tabular-nums">{{ e.total_points }}</span>
        </li>
    </ol>
</template>
