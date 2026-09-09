<script setup>
import { computed, onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';
import { useAudio } from '../../../shared/audio';
import { clip } from '../../logic';
import Avatar from '../../../shared/Avatar.vue';

/** Beat: the podium. Three columns rise, third, second, then the champion. Anonymous: the distribution. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean, still: Boolean });
const audio = useAudio();
const places = computed(() => {
    const byPlace = Object.fromEntries((props.payload.places ?? []).map((p) => [p.place, p]));
    return [2, 1, 3].map((n) => byPlace[n]).filter(Boolean);
});
const heights = { 1: '100%', 2: '72%', 3: '54%' };
const columns = ref(null);
const tops = computed(() => props.payload.top_scores ?? []);

onMounted(() => {
    if (!columns.value) return;
    const cols = [...columns.value.querySelectorAll('[data-place]')].sort((a, b) => Number(b.dataset.place) - Number(a.dataset.place));
    const tl = timeline();
    if (props.still) {
        tl.set(cols, { scaleY: 1 });
        return;
    }
    cols.forEach((col, i) => {
        tl.from(col, { scaleY: 0, transformOrigin: 'bottom center', duration: dur(0.9), ease: 'power3.out' }, i === 0 ? 0.3 : '-=0.3')
            .from(col.querySelector('[data-name]'), { opacity: 0, y: 20, duration: dur(0.4) }, '-=0.4');
        if (col.dataset.place === '1') tl.add(() => audio.cue('podium'), '-=0.9');
    });
});
</script>

<template>
    <section class="flex h-full flex-col items-center">
        <h2 class="text-6xl font-black">{{ anonymous ? 'How the room scored' : 'The podium' }}</h2>

        <div v-if="anonymous" class="mt-12 grid w-full flex-1 grid-cols-3 content-center gap-8 text-center">
            <div class="rounded-3xl bg-slate-900 p-10"><p class="text-3xl uppercase tracking-widest text-slate-400">Lowest</p><p class="mt-4 font-mono text-8xl font-black">{{ payload.distribution?.min }}</p></div>
            <div class="rounded-3xl bg-slate-900 p-10 ring-2 ring-amber-400/60"><p class="text-3xl uppercase tracking-widest text-slate-400">Median</p><p class="mt-4 font-mono text-8xl font-black">{{ payload.distribution?.median }}</p></div>
            <div class="rounded-3xl bg-slate-900 p-10"><p class="text-3xl uppercase tracking-widest text-slate-400">Highest</p><p class="mt-4 font-mono text-8xl font-black text-amber-200">{{ payload.distribution?.max }}</p></div>
            <p class="col-span-3 mt-4 text-4xl text-slate-300">Top three scores: <span class="font-mono text-amber-200">{{ tops.join(' · ') }}</span>. Your rank is on your phone.</p>
        </div>

        <div v-else ref="columns" class="mt-8 grid w-full flex-1 grid-cols-3 items-end gap-10 px-24">
            <div v-for="p in places" :key="p.place" :data-place="p.place" class="flex flex-col justify-end rounded-t-[2.5rem] px-6 pb-8 pt-10 text-center" :class="p.place === 1 ? 'bg-amber-400/90 text-slate-950' : p.place === 2 ? 'bg-slate-300 text-slate-950' : 'bg-amber-800/80 text-amber-50'" :style="{ height: heights[p.place] }">
                <div data-name class="flex flex-col items-center">
                    <Avatar :avatar="p.player.avatar" :name="p.player.username" :size="p.place === 1 ? 7 : 5.5" />
                    <p class="mt-2 text-7xl font-black">{{ p.place }}</p>
                    <p class="mt-3 max-w-full truncate text-5xl font-black" :title="p.player.username">{{ clip(p.player.username, 16) }}</p>
                    <p class="mt-2 font-mono text-4xl font-bold">{{ p.total_points }} pts</p>
                    <p v-if="p.archetype" class="mt-2 text-2xl opacity-80">{{ p.archetype.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
