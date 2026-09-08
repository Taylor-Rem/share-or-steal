<script setup>
import { computed, onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';

/** Beat: share rate by decision index, drawn live so the endgame cliff appears as the line reaches it. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const W = 1400;
const H = 620;
const PAD = { l: 90, r: 40, t: 30, b: 70 };
const series = computed(() => props.payload.series.filter((p) => p.share_rate !== null));
const x = (i) => PAD.l + ((i - 1) / Math.max(1, props.payload.series.length - 1)) * (W - PAD.l - PAD.r);
const y = (r) => PAD.t + (1 - r) * (H - PAD.t - PAD.b);
const path = computed(() => series.value.map((p, k) => `${k === 0 ? 'M' : 'L'}${x(p.decision).toFixed(1)},${y(p.share_rate).toFixed(1)}`).join(' '));
const line = ref(null);
const dots = ref(null);
const length = ref(2000);

onMounted(() => {
    length.value = line.value.getTotalLength();
    const tl = timeline();
    tl.fromTo(line.value, { strokeDashoffset: length.value }, { strokeDashoffset: 0, duration: dur(2.4), ease: 'none' })
        .from(dots.value.children, { scale: 0, transformOrigin: 'center', duration: dur(0.3), stagger: dur(2.4 / Math.max(1, series.value.length)) }, 0);
});
</script>

<template>
    <section class="flex h-full flex-col">
        <h2 class="text-5xl font-black">Share rate by decision</h2>
        <p class="mt-1 text-2xl text-slate-400">Every round, averaged. Watch what happens when the end is in sight.</p>
        <svg :viewBox="`0 0 ${W} ${H}`" class="mt-4 h-full w-full flex-1" role="img" aria-label="Share rate by decision">
            <g v-for="r in [0, 0.25, 0.5, 0.75, 1]" :key="r">
                <line :x1="PAD.l" :x2="W - PAD.r" :y1="y(r)" :y2="y(r)" stroke="#1e293b" stroke-width="2" />
                <text :x="PAD.l - 16" :y="y(r) + 10" text-anchor="end" fill="#64748b" font-size="28" font-family="ui-monospace, monospace">{{ Math.round(r * 100) }}%</text>
            </g>
            <text v-for="p in payload.series" :key="p.decision" :x="x(p.decision)" :y="H - PAD.b + 44" text-anchor="middle" fill="#94a3b8" font-size="30" font-family="ui-monospace, monospace">{{ p.decision }}</text>
            <path ref="line" :d="path" fill="none" stroke="#34d399" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" :stroke-dasharray="length" />
            <g ref="dots">
                <circle v-for="p in series" :key="p.decision" :cx="x(p.decision)" :cy="y(p.share_rate)" r="14" fill="#0f172a" stroke="#34d399" stroke-width="6" />
            </g>
        </svg>
    </section>
</template>
