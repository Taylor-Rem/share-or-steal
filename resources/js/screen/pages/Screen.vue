<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useGameStore } from '../../shared/stores/game';
import { useFixture } from '../../shared/fixtures/useFixture';
import { useAudio } from '../../shared/audio';
import { pushMoments, revealCue } from '../logic';
import Lobby from '../components/Lobby.vue';
import Pairing from '../components/Pairing.vue';
import Decisions from '../components/Decisions.vue';
import RoundSummary from '../components/RoundSummary.vue';
import Analysis from '../components/Analysis.vue';
import Ended from '../components/Ended.vue';
import PausedOverlay from '../components/PausedOverlay.vue';

/**
 * `/screen/{code}` — the projector. No controls. Renders the server's `state` and the
 * public events (CONTRACT.md § 9.1); never a live choice. `?fixture=1` / `?fixture=fast`
 * (+ `&anonymous=1`, `&comparison=1`) plays the scripted game instead.
 */
const props = defineProps({ code: { type: String, required: true } });
const route = useRoute();
const store = useGameStore();
const audio = useAudio();

const loading = ref(true);
const failure = ref(null);
const feed = ref([]);
let fixture = null;

// The projector plays the room's sound. Browsers keep audio locked until a gesture, so
// the first click anywhere on the page (the operator opening it) unlocks it.
audio.use('screen');
function unlock() {
    if (!audio.enabled.value) audio.toggle();
    audio.unlock();
    syncMusic();
}

onMounted(async () => {
    const code = props.code.toUpperCase();
    if (route.query.fixture) {
        fixture = useFixture(store, { code, kind: 'screen', fast: route.query.fixture === 'fast', anonymous: route.query.anonymous === '1', comparison: route.query.comparison === '1' });
        loading.value = false;
        return;
    }
    try {
        await store.connect({ code, kind: 'screen' });
    } catch (error) {
        failure.value = error?.response?.status === 404 ? `No game with code ${code}.` : "Can't reach the game. Reload to try again.";
    } finally {
        loading.value = false;
    }
});
onBeforeUnmount(() => {
    fixture?.stop();
    store.disconnect();
});

// The director can ask the projector to reload itself.
watch(
    () => store.events[0],
    (e) => {
        if (e?.event === 'screen.reload' && !store.isFixture) window.location.reload();
        if (e?.event === 'player.joined') audio.cue('join');
    },
);

// The feed and the reveal's sound.
watch(
    () => store.publicReveal,
    (reveal) => {
        if (!reveal) return;
        feed.value = pushMoments(feed.value, reveal);
        const cue = revealCue(reveal.aggregate);
        if (cue) audio.cue(cue);
    },
);
watch(
    () => store.status,
    (status, was) => {
        if (status === 'pairing') feed.value = [];
        if (status === 'round_summary' && was !== 'round_summary') audio.cue('round_end');
        syncMusic();
    },
);

// The music beds: a lobby loop, a round loop whose drums step up for decisions 8-10, a
// warmer bed under the analysis. Paused rooms go quiet.
function syncMusic() {
    if (!audio.unlocked.value) return;
    const s = store.state;
    if (!s || s.paused) return audio.music(null);
    if (s.status === 'lobby') return audio.music('lobby');
    if (['pairing', 'deciding', 'revealing', 'round_summary'].includes(s.status)) {
        audio.music('round');
        audio.intensity(s.decision !== null && s.decision >= s.decisions_per_round - 2);
        return;
    }
    if (s.status === 'analysis' || s.status === 'finished') return audio.music('analysis');
    audio.music(null);
}
watch(() => [store.state?.decision, store.state?.paused], syncMusic);

const view = computed(() => {
    switch (store.status) {
        case 'pairing': return Pairing;
        case 'deciding':
        case 'revealing': return Decisions;
        case 'round_summary': return RoundSummary;
        case 'analysis': return Analysis;
        case 'finished': return Ended;
        default: return Lobby;
    }
});
</script>

<template>
    <div class="relative h-dvh w-screen overflow-hidden bg-slate-950 text-slate-100 select-none">
        <div v-if="loading" class="flex h-full items-center justify-center text-3xl text-slate-500">Loading…</div>
        <div v-else-if="failure" class="flex h-full items-center justify-center px-16 text-center text-4xl text-rose-200">{{ failure }}</div>
        <component :is="view" v-else :feed="feed" />

        <button
            v-if="!audio.unlocked.value && !loading && !failure"
            type="button"
            class="absolute inset-0 z-30 flex cursor-pointer items-end justify-center bg-transparent pb-10"
            aria-label="Click once to enable sound"
            @click="unlock"
        >
            <span class="sos-pulse rounded-full bg-slate-900/90 px-8 py-3 text-2xl text-slate-200 ring-1 ring-slate-600">🔊 Click anywhere once to enable sound</span>
        </button>

        <div v-if="!store.isFixture && store.connection !== 'connected' && !loading && !failure" class="sos-pulse absolute right-6 top-6 rounded-full bg-amber-500/20 px-4 py-1.5 text-lg text-amber-200">reconnecting…</div>
        <div v-if="store.isFixture" class="absolute left-6 top-6 rounded-full bg-violet-500/20 px-3 py-1 text-sm text-violet-300">fixture</div>
        <PausedOverlay v-if="store.isPaused" />
    </div>
</template>
