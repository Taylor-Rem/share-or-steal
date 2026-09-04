<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '../../shared/stores/game';
import { deviceToken, remember } from '../../shared/device';
import { useFixture } from '../../shared/fixtures/useFixture';
import { useAudio } from '../../shared/audio';
import { vibrate, BUZZ } from '../../shared/haptics';
import TopBar from '../components/TopBar.vue';
import Lobby from '../components/Lobby.vue';
import Pairing from '../components/Pairing.vue';
import Deciding from '../components/Deciding.vue';
import Revealing from '../components/Revealing.vue';
import RoundSummary from '../components/RoundSummary.vue';
import Analysis from '../components/Analysis.vue';
import Ended from '../components/Ended.vue';
import InProgress from '../components/InProgress.vue';
import Kicked from '../components/Kicked.vue';
import NotInRound from '../components/NotInRound.vue';
import PausedOverlay from '../components/PausedOverlay.vue';
import Nudge from '../components/Nudge.vue';

/**
 * `/play/{code}` — the phone. Reconnects from GET me on every load (CONTRACT.md § 12), or
 * plays the scripted fixture with `?fixture=1` / `?fixture=fast`. Renders one component
 * per status; the server's `state` decides, never the phone.
 */
const props = defineProps({ code: { type: String, required: true } });
const route = useRoute();
const router = useRouter();
const store = useGameStore();
const audio = useAudio();

const loading = ref(true);
const failure = ref(null);
let fixture = null;

onMounted(async () => {
    const code = props.code.toUpperCase();
    remember('last_code', code);

    if (route.query.fixture) {
        fixture = useFixture(store, { code, fast: route.query.fixture === 'fast', anonymous: route.query.anonymous === '1' });
        loading.value = false;
        return;
    }

    try {
        const result = await store.connectPhone({ code, deviceToken: deviceToken() });
        if (result === 'not_joined') {
            router.replace({ path: '/', query: { code, reason: 'not_joined' } });
            return;
        }
    } catch (error) {
        failure.value = error?.response?.status === 404 ? `No game with code ${code}.` : "Can't reach the game. Check your connection and reload.";
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    fixture?.stop();
    store.disconnect();
});

// Feel: a buzz on every reveal, a double buzz when stolen from, and the matching cue.
watch(
    () => store.lastReveal,
    (reveal, previous) => {
        if (!reveal || reveal === previous) return;
        const stolen = reveal.outcome === 'betrayed';
        vibrate(stolen ? BUZZ.stolen : BUZZ.reveal);
        audio.cue(reveal.outcome === 'betrayed' || reveal.outcome === 'betrayer' ? 'betrayal' : reveal.outcome);
    },
);
watch(
    () => store.roundSummary,
    (summary) => summary && audio.cue('round_end'),
);
watch(
    () => store.card,
    (card) => {
        if (!card) return;
        vibrate(BUZZ.reveal);
        audio.cue(card.type === 'award' ? 'award' : 'card');
    },
);
watch(
    () => store.nudge,
    (n) => n && vibrate(BUZZ.nudge),
);

const view = computed(() => {
    if (store.kicked) return Kicked;
    if (!store.isAdmitted) return InProgress;
    const status = store.status;
    if (status === 'finished') return Ended;
    if (status === 'analysis') return Analysis;
    if (status === 'lobby' || !status) return Lobby;
    if (!store.inCurrentRound) return NotInRound;
    return { pairing: Pairing, deciding: Deciding, revealing: Revealing, round_summary: RoundSummary }[status] ?? Lobby;
});
</script>

<template>
    <div class="flex min-h-dvh flex-col pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)]">
        <TopBar />

        <main v-if="loading" class="flex flex-1 items-center justify-center text-slate-400">Loading…</main>
        <main v-else-if="failure" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
            <p class="text-lg">{{ failure }}</p>
            <router-link to="/" class="rounded-xl bg-slate-800 px-5 py-3 font-semibold">Back to join</router-link>
        </main>
        <component :is="view" v-else class="flex-1" />

        <Nudge v-if="store.nudge && store.status === 'deciding'" />
        <PausedOverlay v-if="store.isPaused" />
    </div>
</template>
