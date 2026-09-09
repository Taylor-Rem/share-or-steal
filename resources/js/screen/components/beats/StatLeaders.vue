<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { from, gsap, dur } from '../../anim';
import { reducedMotion } from '../../../shared/motion';
import { clip } from '../../logic';

/** Beat: the stat leaders as a rotating board, three at a time. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const leaders = computed(() => props.payload.leaders ?? []);
const page = ref(0);
const perPage = 3;
const pages = computed(() => Math.max(1, Math.ceil(leaders.value.length / perPage)));
const visible = computed(() => leaders.value.slice(page.value * perPage, page.value * perPage + perPage));
const board = ref(null);
let timer = null;

const enter = () => board.value && from(board.value.children, { y: 40, opacity: 0, duration: dur(0.5), stagger: dur(0.12), ease: 'power3.out' });
onMounted(() => {
    enter();
    if (pages.value > 1) {
        timer = setInterval(async () => {
            if (!board.value) return;
            if (!reducedMotion()) await gsap.to(board.value.children, { y: -30, opacity: 0, duration: 0.3, stagger: 0.05 });
            page.value = (page.value + 1) % pages.value;
            await Promise.resolve();
            enter();
        }, 3200);
    }
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section class="flex h-full flex-col">
        <div class="flex items-baseline justify-between">
            <h2 class="text-5xl font-black">Stat leaders</h2>
            <p class="font-mono text-2xl text-slate-500">{{ page + 1 }} / {{ pages }}</p>
        </div>
        <div ref="board" class="mt-8 grid flex-1 grid-cols-3 gap-8">
            <div v-for="l in visible" :key="l.stat" class="flex flex-col items-center justify-center rounded-[2.5rem] bg-slate-900 p-10 text-center">
                <p class="text-3xl uppercase tracking-widest text-slate-400">{{ l.label }}</p>
                <p class="mt-6 font-mono text-8xl font-black text-emerald-300">{{ l.value_label }}</p>
                <p v-if="l.player" class="mt-6 max-w-full truncate text-5xl font-bold" :title="l.player.username">{{ clip(l.player.username, 16) }}</p>
                <p v-else class="mt-6 text-3xl text-slate-500">on someone's phone</p>
            </div>
        </div>
    </section>
</template>
