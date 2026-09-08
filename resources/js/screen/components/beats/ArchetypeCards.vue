<script setup>
import { onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';
import { useAudio } from '../../../shared/audio';

/** Beat (anonymous): every archetype card goes to a phone; the screen shows a stack fanning out. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const audio = useAudio();
const stack = ref(null);
const cards = Array.from({ length: Math.min(props.payload.count, 12) }, (_, i) => i);

onMounted(() => {
    const tl = timeline();
    tl.from(stack.value.children, { y: 200, opacity: 0, rotate: 0, duration: dur(0.6), stagger: dur(0.08), ease: 'back.out(1.4)' }).add(() => audio.cue('card'), 0.2);
});
</script>

<template>
    <section class="flex h-full flex-col items-center justify-center gap-12">
        <div ref="stack" class="relative flex h-72 items-end">
            <div v-for="i in cards" :key="i" class="absolute bottom-0 h-64 w-44 rounded-3xl border border-violet-400/40 bg-slate-800 shadow-2xl" :style="{ left: `${(i - cards.length / 2) * 3.2}rem`, transform: `rotate(${(i - cards.length / 2) * 5}deg)`, transformOrigin: 'bottom center' }" />
        </div>
        <p class="text-8xl font-black">{{ payload.count }} cards dealt</p>
        <p class="text-4xl text-slate-400">Your archetype is on your phone. Nobody else's is.</p>
    </section>
</template>
