<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../composables/useCountdown';
import PartnerName from './PartnerName.vue';
import Track from './Track.vue';

const store = useGameStore();
const reveal = computed(() => store.lastReveal);
const partnerName = computed(() => store.partner?.display_name ?? 'your partner');
const deadline = computed(() => reveal.value?.next_at ?? store.state?.phase_ends_at);
const { seconds } = useCountdown(store.clock, deadline);

const headline = computed(() => {
    const r = reveal.value;
    if (!r) return 'Scoring…';
    return {
        mutual_share: 'You both shared',
        mutual_steal: 'You both stole',
        betrayed: `${partnerName.value} stole from you`,
        betrayer: `You stole from ${partnerName.value}`,
    }[r.outcome];
});
const tone = computed(() => ({ mutual_share: 'emerald', mutual_steal: 'slate', betrayed: 'rose', betrayer: 'amber' })[reveal.value?.outcome] ?? 'slate');
const flash = { emerald: 'bg-emerald-500', rose: 'bg-rose-500', amber: 'bg-amber-400', slate: 'bg-slate-500' };
const text = { emerald: 'text-emerald-300', rose: 'text-rose-300', amber: 'text-amber-300', slate: 'text-slate-300' };
const choiceClass = (c) => (c === 'share' ? 'bg-emerald-500 text-slate-950' : 'bg-rose-500 text-white');
</script>

<template>
    <main class="relative flex flex-col items-center gap-5 px-5 py-4">
        <div :key="reveal?.decision" class="sos-flash pointer-events-none absolute inset-0" :class="flash[tone]" aria-hidden="true" />

        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Decision {{ reveal?.decision ?? store.state?.decision }} of {{ store.decisionsPerRound }}</p>
        </div>

        <Track />

        <template v-if="reveal">
            <h2 :key="reveal.decision" class="sos-rise text-center text-3xl font-black" :class="text[tone]">{{ headline }}</h2>

            <div class="grid w-full max-w-sm grid-cols-2 gap-4">
                <div :key="'y' + reveal.decision" class="sos-pop flex flex-col items-center gap-2 rounded-3xl bg-slate-900 p-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">You</span>
                    <span class="rounded-xl px-4 py-2 text-xl font-black capitalize" :class="choiceClass(reveal.you.choice)">{{ reveal.you.choice }}</span>
                    <span class="font-mono text-4xl font-bold tabular-nums">+{{ reveal.you.points }}</span>
                    <span v-if="reveal.you.timed_out" class="text-xs text-amber-300">no tap · counted as share</span>
                    <span v-else-if="reveal.you.response_ms !== null" class="text-xs text-slate-500">{{ (reveal.you.response_ms / 1000).toFixed(1) }} s</span>
                </div>
                <div :key="'p' + reveal.decision" class="sos-pop flex flex-col items-center gap-2 rounded-3xl bg-slate-900 p-4">
                    <span class="max-w-full truncate text-xs font-semibold uppercase tracking-wider text-slate-400"><PartnerName /></span>
                    <span class="rounded-xl px-4 py-2 text-xl font-black capitalize" :class="choiceClass(reveal.partner.choice)">{{ reveal.partner.choice }}</span>
                    <span class="font-mono text-4xl font-bold tabular-nums">+{{ reveal.partner.points }}</span>
                    <span v-if="reveal.partner.timed_out" class="text-xs text-amber-300">no tap</span>
                </div>
            </div>

            <div class="flex w-full max-w-sm justify-between rounded-2xl bg-slate-900/60 px-5 py-3 font-mono text-sm">
                <span>Round: <b class="text-white">{{ reveal.round_total.you }}</b> – {{ reveal.round_total.partner }}</span>
                <span>Game: <b class="text-white">{{ reveal.total_points }}</b></span>
            </div>
        </template>
        <p v-else class="text-slate-400">Scoring…</p>

        <p class="mt-auto text-sm text-slate-400">
            <template v-if="reveal?.is_last">Round over in <span class="font-mono font-bold text-white">{{ seconds }}</span></template>
            <template v-else>Next decision in <span class="font-mono font-bold text-white">{{ seconds }}</span></template>
        </p>
    </main>
</template>
