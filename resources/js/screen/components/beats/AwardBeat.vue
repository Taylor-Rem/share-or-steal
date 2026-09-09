<script setup>
import { onMounted, ref } from 'vue';
import { timeline, dur } from '../../anim';
import { useAudio } from '../../../shared/audio';
import { clip } from '../../logic';
import Avatar from '../../../shared/Avatar.vue';

/** Beat: one award. The name waits behind a drumroll, then lands. Anonymous: the award only. */
const props = defineProps({ payload: { type: Object, required: true }, anonymous: Boolean });
const audio = useAudio();
const award = props.payload.award;
const revealed = ref(false);
const title = ref(null);
const name = ref(null);
const dots = ref(null);

onMounted(() => {
    const tl = timeline();
    tl.from(title.value, { scale: 0.7, opacity: 0, duration: dur(0.6), ease: 'back.out(1.6)' })
        .add(() => audio.cue('award'))
        .to(dots.value, { opacity: 1, duration: dur(0.2) });
    if (award.winner) {
        tl.to(dots.value.children, { y: -14, duration: dur(0.25), stagger: { each: dur(0.12), repeat: 5, yoyo: true } })
            .to(dots.value, { opacity: 0, duration: dur(0.15) })
            .add(() => (revealed.value = true))
            .add(() => audio.cue('award_hit'))
            .add(() => name.value && timeline().from(name.value, { scale: 0.4, opacity: 0, duration: dur(0.6), ease: 'elastic.out(1, 0.5)' }), '+=0.05');
    } else {
        tl.to(dots.value, { opacity: 0, duration: dur(0.15) }).add(() => (revealed.value = true));
    }
});
</script>

<template>
    <section class="flex h-full flex-col items-center justify-center gap-8 text-center">
        <div ref="title">
            <div class="text-9xl" aria-hidden="true">🏆</div>
            <h2 class="mt-4 text-8xl font-black">{{ award.label }}</h2>
            <p class="mt-3 text-3xl text-slate-400">{{ award.description }}</p>
        </div>
        <div class="relative flex h-40 w-full items-center justify-center">
            <div ref="dots" class="absolute flex gap-6 opacity-0"><span v-for="i in 3" :key="i" class="h-8 w-8 rounded-full bg-amber-300" /></div>
            <div v-if="revealed && award.winner" ref="name" class="flex flex-col items-center">
                <p class="flex max-w-[80rem] items-center gap-6 text-[7rem] font-black leading-none text-amber-200" :title="award.winner.username"><Avatar :avatar="award.winner.avatar" :name="award.winner.username" :size="7" /><span class="truncate">{{ clip(award.winner.username, 20) }}</span></p>
                <p class="mt-4 font-mono text-4xl text-slate-300">{{ award.value_label }}<span v-if="award.tie_break === 'coin_flip'" class="ml-4 rounded-full bg-slate-800 px-4 py-1 text-2xl text-slate-400">🪙 coin flip</span><span v-else-if="award.tie_break === 'points'" class="ml-4 rounded-full bg-slate-800 px-4 py-1 text-2xl text-slate-400">tie, on points</span></p>
            </div>
            <p v-else-if="revealed" class="sos-pop text-5xl text-violet-200">The winner's phone knows.</p>
        </div>
    </section>
</template>
