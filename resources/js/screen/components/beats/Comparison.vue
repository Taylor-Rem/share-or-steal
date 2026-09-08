<script setup>
import { onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';
import { pct } from '../../logic';

/** Beat: this room, last game versus this one. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const root = ref(null);
const rows = [
    ['Share rate', props.payload.share_rate, pct],
    ['Forgiveness', props.payload.forgiveness, pct],
    ['Betrayals each', props.payload.betrayals, (v) => (v === null || v === undefined ? '—' : Number(v).toFixed(1))],
];

onMounted(() => {
    timeline().from(root.value.children, { y: 40, opacity: 0, duration: dur(0.6), stagger: dur(0.2), ease: 'power3.out' });
});
</script>

<template>
    <section ref="root" class="flex h-full flex-col items-center justify-center gap-12 text-center">
        <p class="max-w-[80rem] text-6xl font-black leading-tight">{{ payload.text }}</p>
        <div class="grid w-2/3 grid-cols-3 gap-8">
            <div v-for="[label, pair, f] in rows" :key="label" class="rounded-3xl bg-slate-900 p-8">
                <p class="text-3xl uppercase tracking-widest text-slate-400">{{ label }}</p>
                <p class="mt-4 font-mono text-5xl"><span class="text-slate-500">{{ f(pair?.before) }}</span> <span class="text-slate-600">→</span> <span class="font-black text-white">{{ f(pair?.after) }}</span></p>
            </div>
        </div>
    </section>
</template>
