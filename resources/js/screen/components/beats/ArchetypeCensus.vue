<script setup>
import { computed, onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';

/** Beat: how many of each archetype the room produced. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const rows = computed(() => [...props.payload.counts].sort((a, b) => b.count - a.count));
const max = computed(() => Math.max(1, ...rows.value.map((r) => r.count)));
const list = ref(null);

onMounted(() => {
    const tl = timeline();
    tl.from(list.value.children, { x: -60, opacity: 0, duration: dur(0.5), stagger: dur(0.12), ease: 'power3.out' })
        .from(list.value.querySelectorAll('[data-bar]'), { scaleX: 0, transformOrigin: 'left center', duration: dur(0.8), stagger: dur(0.12), ease: 'power2.out' }, '-=0.6');
});
</script>

<template>
    <section class="flex h-full flex-col">
        <h2 class="text-5xl font-black">The census</h2>
        <p class="mt-1 text-2xl text-slate-400">What kind of room this was.</p>
        <ul ref="list" class="mt-8 flex flex-1 flex-col justify-center gap-4">
            <li v-for="r in rows" :key="r.archetype.key" class="grid grid-cols-[22rem_1fr_6rem] items-center gap-6">
                <span class="text-right text-4xl font-bold">{{ r.archetype.label }}</span>
                <div class="h-14 overflow-hidden rounded-2xl bg-slate-800"><div data-bar class="h-full rounded-2xl bg-emerald-400/80" :style="{ width: `${(r.count / max) * 100}%` }" /></div>
                <span class="font-mono text-5xl font-black tabular-nums">{{ r.count }}</span>
            </li>
        </ul>
    </section>
</template>
