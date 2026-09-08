<script setup>
import { onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';

/** Beat: the room's share rate, and its points against the "everyone cooperates" score. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const shown = ref({ rate: 0, points: 0 });
const bar = ref(null);
const root = ref(null);

onMounted(() => {
    const target = { rate: Math.round((props.payload.share_rate ?? 0) * 100), points: props.payload.total_points };
    const tl = timeline();
    tl.from(root.value.children, { y: 40, opacity: 0, duration: dur(0.6), stagger: dur(0.2), ease: 'power3.out' })
        .to(shown.value, { rate: target.rate, points: target.points, duration: dur(1.8), ease: 'power2.out', onUpdate: () => { shown.value.rate = Math.round(shown.value.rate); shown.value.points = Math.round(shown.value.points); } }, '-=0.3')
        .fromTo(bar.value, { width: '0%' }, { width: `${Math.min(100, (props.payload.total_points / Math.max(1, props.payload.max_cooperative_points)) * 100)}%`, duration: dur(1.6), ease: 'power2.out' }, '<');
});
</script>

<template>
    <section ref="root" class="flex h-full flex-col items-center justify-center gap-10 text-center">
        <p class="text-4xl uppercase tracking-[0.3em] text-slate-400">The room shared</p>
        <p class="text-[16rem] font-black leading-none tabular-nums" :class="shown.rate >= 50 ? 'text-emerald-300' : 'text-rose-300'">{{ shown.rate }}<span class="text-[8rem] text-slate-500">%</span></p>
        <div class="w-2/3">
            <div class="flex justify-between text-3xl text-slate-300">
                <span><b class="font-mono text-white">{{ shown.points }}</b> points earned</span>
                <span>everyone-shares score <b class="font-mono text-white">{{ payload.max_cooperative_points }}</b></span>
            </div>
            <div class="mt-3 h-8 w-full overflow-hidden rounded-full bg-slate-800"><div ref="bar" class="h-full rounded-full bg-emerald-400" /></div>
        </div>
    </section>
</template>
