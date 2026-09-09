<script setup>
import { nextTick, ref, watch } from 'vue';
import { from, dur } from '../anim';

/** The moments feed: newest at the top, each new item sliding in. */
const props = defineProps({ items: { type: Array, required: true } });
const list = ref(null);
const tones = {
    betrayal: 'border-rose-500/60 bg-rose-500/10 text-rose-100',
    mutual_steal: 'border-slate-500/60 bg-slate-500/10 text-slate-200',
    mutual_share_streak: 'border-emerald-500/60 bg-emerald-500/10 text-emerald-100',
    comeback: 'border-amber-400/60 bg-amber-400/10 text-amber-100',
};
const icons = { betrayal: '🗡', mutual_steal: '💥', mutual_share_streak: '🤝', comeback: '🔥' };

watch(
    () => props.items[0]?.key,
    async () => {
        await nextTick();
        const fresh = [...(list.value?.children ?? [])].filter((el) => el.dataset.fresh === '1');
        if (fresh.length) from(fresh, { x: 60, opacity: 0, duration: dur(0.45), stagger: dur(0.08), ease: 'power3.out' });
    },
);
</script>

<template>
    <ul ref="list" class="flex flex-col gap-2 overflow-hidden">
        <li
            v-for="(m, i) in items"
            :key="m.key"
            :data-fresh="i === 0 || (items[0] && items[i].decision === items[0].decision && items[i].round === items[0].round) ? '1' : '0'"
            class="flex items-center gap-3 rounded-2xl border px-5 py-3 text-2xl"
            :class="tones[m.type] ?? tones.mutual_steal"
        >
            <span aria-hidden="true">{{ icons[m.type] ?? '•' }}</span>
            <span class="truncate">{{ m.text }}</span>
        </li>
    </ul>
</template>
