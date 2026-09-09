<script setup>
import { onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';
import { useAudio } from '../../../shared/audio';
import { clip, ordinal, pct } from '../../logic';
import Avatar from '../../../shared/Avatar.vue';

/** Beat: one player's archetype, as a card that flips over. Their phone gets the same card. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const audio = useAudio();
const card = ref(null);
const front = ref(null);
const back = ref(null);

onMounted(() => {
    const tl = timeline();
    tl.from(card.value, { y: 60, opacity: 0, duration: dur(0.5), ease: 'power3.out' })
        .add(() => audio.cue('card'), '+=0.4')
        .to(front.value, { rotateY: -90, duration: dur(0.35), ease: 'power2.in' })
        .set(front.value, { visibility: 'hidden' })
        .set(back.value, { visibility: 'visible' })
        .fromTo(back.value, { rotateY: 90 }, { rotateY: 0, duration: dur(0.45), ease: 'power2.out' })
        .from(back.value.querySelectorAll('[data-stat]'), { y: 20, opacity: 0, duration: dur(0.4), stagger: dur(0.06) }, '-=0.1');
});

const stats = [
    ['Share rate', (s) => pct(s.share_rate)],
    ['Opening move', (s) => pct(s.opening_move)],
    ['Forgiveness', (s) => pct(s.forgiveness)],
    ['Retaliation', (s) => pct(s.retaliation)],
    ['Betrayals', (s) => s.betrayals],
    ['Stolen from', (s) => s.times_stolen_from],
];
</script>

<template>
    <section class="flex h-full items-center justify-center" style="perspective: 2000px">
        <div ref="card" class="relative h-[34rem] w-[64rem]">
            <div ref="front" class="absolute inset-0 flex flex-col items-center justify-center rounded-[3rem] bg-slate-800 shadow-2xl" style="backface-visibility: hidden">
                <Avatar :avatar="payload.player.avatar" :name="payload.player.username" :size="9" />
                <p class="mt-6 text-4xl uppercase tracking-[0.3em] text-slate-400">{{ ordinal(payload.rank) }} · {{ payload.total_points }} pts</p>
                <p class="mt-4 max-w-[56rem] truncate px-8 text-8xl font-black">{{ clip(payload.player.username, 22) }}</p>
                <p class="mt-6 text-5xl text-slate-500">is…</p>
            </div>
            <div ref="back" class="absolute inset-0 grid grid-cols-[1.2fr_1fr] gap-8 rounded-[3rem] border border-emerald-400/40 bg-slate-900 p-12 shadow-2xl" style="backface-visibility: hidden; visibility: hidden">
                <div class="flex flex-col justify-center">
                    <p class="flex items-center gap-4 text-4xl font-semibold text-slate-300"><Avatar :avatar="payload.player.avatar" :name="payload.player.username" :size="3.5" /><span class="truncate">{{ clip(payload.player.username, 20) }}</span></p>
                    <p class="mt-2 text-8xl font-black text-emerald-300">{{ payload.archetype?.label ?? 'Unclassified' }}</p>
                    <p class="mt-4 text-3xl leading-snug text-slate-300">{{ payload.archetype?.blurb }}</p>
                </div>
                <dl class="grid content-center gap-3 text-3xl">
                    <template v-for="[label, f] in stats" :key="label">
                        <div data-stat class="flex justify-between rounded-2xl bg-slate-800 px-6 py-3"><dt class="text-slate-400">{{ label }}</dt><dd class="font-mono font-bold">{{ f(payload) }}</dd></div>
                    </template>
                </dl>
            </div>
        </div>
    </section>
</template>
