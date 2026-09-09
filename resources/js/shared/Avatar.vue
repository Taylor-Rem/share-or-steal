<script setup>
import { computed } from 'vue';
import { SWATCHES } from './avatars';

/**
 * A player's picked look: an emoji on a coloured disc. `size` is the disc in rem.
 * No avatar (or a codename) gets a neutral disc with the first letter of the name.
 */
const props = defineProps({
    avatar: { type: Object, default: null },
    name: { type: String, default: '' },
    size: { type: Number, default: 2.5 },
});


const cls = computed(() => (props.avatar ? SWATCHES[props.avatar.color] ?? 'bg-slate-600' : 'bg-slate-700'));
</script>

<template>
    <span
        class="inline-flex shrink-0 select-none items-center justify-center rounded-full leading-none"
        :class="cls"
        :style="{ width: `${size}rem`, height: `${size}rem`, fontSize: `${size * (avatar ? 0.58 : 0.42)}rem` }"
        :title="name"
        aria-hidden="true"
    >
        <template v-if="avatar">{{ avatar.emoji }}</template>
        <span v-else class="font-bold text-slate-300">{{ (name || '?').slice(0, 1).toUpperCase() }}</span>
    </span>
</template>
