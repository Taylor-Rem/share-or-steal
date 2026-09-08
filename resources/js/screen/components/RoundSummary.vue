<script setup>
import { computed, onMounted, ref } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../../shared/useCountdown';
import { gsap, dur } from '../anim';
import { pct } from '../logic';
import Leaderboard from './Leaderboard.vue';

const store = useGameStore();
const summary = computed(() => store.publicSummary);
const anonymous = computed(() => store.state?.mode === 'anonymous');
const deadline = computed(() => store.state?.phase_ends_at);
const { seconds } = useCountdown(store.clock, deadline);
const side = ref(null);

onMounted(() => {
    if (side.value) gsap.from(side.value.children, { x: 60, opacity: 0, duration: dur(0.7), stagger: dur(0.2), delay: dur(0.2), ease: 'power3.out' });
});
</script>

<template>
    <main class="flex h-full flex-col px-16 py-10">
        <header class="flex items-end justify-between">
            <h1 class="text-7xl font-black">Round {{ summary?.round ?? store.state?.round }} <span class="text-slate-500">scoreboard</span></h1>
            <p class="text-3xl text-slate-400">
                <template v-if="summary?.is_last">The analysis is next</template>
                <template v-else>Next round in <span class="font-mono font-bold text-white">{{ seconds }}</span></template>
            </p>
        </header>

        <div v-if="!summary" class="flex flex-1 items-center justify-center text-4xl text-slate-500">Adding it up…</div>

        <div v-else-if="anonymous" class="grid flex-1 grid-cols-2 content-center gap-8">
            <div class="rounded-3xl bg-slate-900 p-12 text-center"><p class="text-[8rem] font-black leading-none text-emerald-300">{{ pct(summary.aggregate.share_rate) }}</p><p class="mt-3 text-3xl text-slate-400">of choices were Share</p></div>
            <div class="grid grid-rows-3 gap-4 text-3xl">
                <div class="flex items-center justify-between rounded-3xl bg-slate-900 px-10"><span class="text-slate-400">Both shared</span><span class="font-mono text-5xl font-bold text-emerald-300">{{ summary.aggregate.mutual_share }}</span></div>
                <div class="flex items-center justify-between rounded-3xl bg-slate-900 px-10"><span class="text-slate-400">Betrayals</span><span class="font-mono text-5xl font-bold text-rose-300">{{ summary.aggregate.betrayals }}</span></div>
                <div class="flex items-center justify-between rounded-3xl bg-slate-900 px-10"><span class="text-slate-400">Both stole</span><span class="font-mono text-5xl font-bold text-slate-300">{{ summary.aggregate.mutual_steal }}</span></div>
            </div>
        </div>

        <div v-else class="mt-8 grid min-h-0 flex-1 grid-cols-[1.3fr_1fr] gap-12">
            <section class="min-h-0 overflow-hidden">
                <Leaderboard :entries="summary.leaderboard" :limit="summary.leaderboard.length > 16 ? 16 : 30" />
                <p v-if="summary.leaderboard.length > 16" class="mt-2 text-xl text-slate-500">…and {{ summary.leaderboard.length - 16 }} more</p>
            </section>
            <aside ref="side" class="flex flex-col gap-6">
                <div class="rounded-3xl border border-rose-500/50 bg-rose-500/10 p-8">
                    <p class="text-2xl uppercase tracking-widest text-rose-300">Biggest betrayal</p>
                    <p class="mt-2 text-4xl font-bold leading-tight text-rose-50">{{ summary.biggest_betrayal?.text ?? 'Nobody stole from a sharer. Suspicious.' }}</p>
                </div>
                <div class="rounded-3xl border border-emerald-500/50 bg-emerald-500/10 p-8">
                    <p class="text-2xl uppercase tracking-widest text-emerald-300">Most cooperative pair</p>
                    <p class="mt-2 text-4xl font-bold leading-tight text-emerald-50">{{ summary.most_cooperative_pair?.text ?? 'Nobody managed a mutual share.' }}</p>
                </div>
                <div class="rounded-3xl bg-slate-900 p-8 text-3xl text-slate-300">
                    Room shared <b class="text-white">{{ pct(summary.aggregate.share_rate) }}</b> · {{ summary.aggregate.betrayals }} betrayals · {{ summary.aggregate.mutual_steal }} mutual steals
                </div>
            </aside>
        </div>
    </main>
</template>
