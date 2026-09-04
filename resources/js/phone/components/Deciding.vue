<script setup>
import { computed, watch } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useCountdown } from '../composables/useCountdown';
import { useAudio } from '../../shared/audio';
import { vibrate, BUZZ } from '../../shared/haptics';
import PartnerName from './PartnerName.vue';
import Ring from './Ring.vue';
import Track from './Track.vue';

const store = useGameStore();
const audio = useAudio();

const decision = computed(() => store.decision);
const deadline = computed(() => decision.value?.deadline_at);
const total = computed(() => decision.value?.choose_ms ?? (deadline.value && decision.value?.opened_at ? Date.parse(deadline.value) - Date.parse(decision.value.opened_at) : 5000));
const { remaining, fraction, seconds } = useCountdown(store.clock, deadline, total);

const open = computed(() => Boolean(decision.value) && decision.value.index === store.state?.decision && remaining.value > 0);
const chosen = computed(() => decision.value?.chosen ?? false);
const canTap = computed(() => open.value && !chosen.value && !store.isPaused);
const urgent = computed(() => open.value && seconds.value <= 2);

// A soft tick each second, louder for the last two (Session 6 decides what that sounds like).
watch(seconds, (s, was) => {
    if (open.value && s !== was && s > 0) audio.cue('tick');
});

function tap(choice) {
    if (!canTap.value) return;
    vibrate(BUZZ.tap);
    audio.cue('lockin');
    store.choose(choice);
}

const rejection = computed(() => {
    const r = decision.value?.rejected;
    if (!r || r === 'already_chosen') return null;
    return { paused: 'Paused. Hang on.', deadline_passed: "Too late. That one's counted as Share.", not_deciding: "Too late. That one's counted as Share.", network: "Couldn't reach the game." }[r] ?? "That didn't go through.";
});
</script>

<template>
    <main class="flex flex-col items-center gap-5 px-5 py-4">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Decision {{ decision?.index ?? store.state?.decision }} of {{ store.decisionsPerRound }}</p>
            <p class="mt-1 text-slate-300">against <PartnerName /></p>
        </div>

        <Track :current="decision?.index ?? null" />

        <Ring :fraction="chosen ? 0 : fraction" :seconds="seconds" :urgent="urgent && !chosen" />

        <p class="h-6 text-center text-sm" :class="rejection ? 'text-rose-300' : 'text-slate-400'" aria-live="polite">
            <template v-if="rejection">{{ rejection }}</template>
            <template v-else-if="chosen">Locked in. Waiting for <PartnerName />…</template>
            <template v-else-if="!open && remaining === 0">Time's up. No tap counts as Share.</template>
            <template v-else-if="!open">Waiting for the next decision…</template>
            <template v-else>Tap one</template>
        </p>

        <div class="grid w-full max-w-sm flex-1 grid-cols-2 gap-4" role="group" aria-label="Your choice">
            <button
                type="button"
                class="flex min-h-40 touch-manipulation flex-col items-center justify-center rounded-3xl text-3xl font-black transition active:scale-95 disabled:opacity-40 motion-reduce:transition-none"
                :class="decision?.your_choice === 'share' ? 'bg-emerald-400 text-slate-950 ring-4 ring-emerald-200 disabled:opacity-100' : 'bg-emerald-600/90 text-white'"
                :disabled="!canTap"
                @click="tap('share')"
            >
                <span>Share</span>
                <span class="mt-1 text-xs font-semibold opacity-80">3 each · or 0 if they steal</span>
            </button>
            <button
                type="button"
                class="flex min-h-40 touch-manipulation flex-col items-center justify-center rounded-3xl text-3xl font-black transition active:scale-95 disabled:opacity-40 motion-reduce:transition-none"
                :class="decision?.your_choice === 'steal' ? 'bg-rose-400 text-slate-950 ring-4 ring-rose-200 disabled:opacity-100' : 'bg-rose-600/90 text-white'"
                :disabled="!canTap"
                @click="tap('steal')"
            >
                <span>Steal</span>
                <span class="mt-1 text-xs font-semibold opacity-80">5 if they share · 1 if both</span>
            </button>
        </div>

        <p class="text-xs text-slate-500">Round total: {{ store.roundTotal.you }} · Game: {{ store.totalPoints }}</p>
    </main>
</template>
