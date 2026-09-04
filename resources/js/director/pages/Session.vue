<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { useDirector } from '../useDirector';
import { beatLabel, explain, primaryAction } from '../logic';
import Banner from '../components/Banner.vue';
import ConfirmButton from '../components/ConfirmButton.vue';
import PlayerList from '../components/PlayerList.vue';
import StateLine from '../components/StateLine.vue';

/**
 * `/director/{code}` — the control panel. State and events from the store (session,
 * screen and director channels); commands through useDirector(). Every failure is shown.
 */
const props = defineProps({ code: { type: String, required: true } });
const store = useGameStore();
const director = useDirector();
const code = props.code.toUpperCase();

const errors = ref([]);
const busy = ref(null); // the action in flight
const busyPlayer = ref(null);
const beats = ref(null); // from GET analysis, once in analysis
const loading = ref(true);
const failure = ref(null);

function fail(e, verb) {
    errors.value = [explain(e, verb), ...errors.value].slice(0, 3);
}

async function loadDetail() {
    const data = await director.session(code);
    store.setDirectorPlayers(data.players);
    store.state = data.state;
    store.clock.sync(data.server_time);
}

onMounted(async () => {
    try {
        await store.connect({ code, kind: 'director', directorKey: director.key.value });
        await loadDetail();
    } catch (e) {
        failure.value = e?.response?.status === 404 ? `No session with code ${code}.` : "Can't reach the server.";
    } finally {
        loading.value = false;
    }
});
onBeforeUnmount(() => store.disconnect());

// Back from a dropped connection: the events we missed are gone, so refetch the truth.
watch(
    () => store.connection,
    (now, was) => {
        if (now === 'connected' && was && was !== 'connecting' && was !== 'idle') loadDetail().catch((e) => fail(e, 'refresh'));
    },
);

// In the analysis, know the beat list so the button can say what comes next.
watch(
    () => store.state?.status,
    async (status) => {
        if ((status === 'analysis' || status === 'finished') && !beats.value) {
            try {
                beats.value = (await director.analysis(code)).beats;
            } catch (e) {
                fail(e, 'load the beats');
            }
        }
    },
    { immediate: true },
);

const primary = computed(() => primaryAction(store.state));
const currentBeat = computed(() => beats.value?.[store.state?.analysis_beat ?? -1] ?? null);
const nextBeat = computed(() => beats.value?.[(store.state?.analysis_beat ?? -1) + 1] ?? null);
const finished = computed(() => store.state?.status === 'finished');
const canPause = computed(() => store.state && !store.state.paused && !finished.value && !['pairing', 'deciding', 'revealing', 'round_summary'].includes(store.state.status));

async function run(action, verb = action) {
    busy.value = action;
    try {
        const data = await director.command(code, action);
        if (data?.state) store.state = data.state;
    } catch (e) {
        fail(e, verb);
    } finally {
        busy.value = null;
    }
}
async function admit(p) {
    busyPlayer.value = p.id;
    try {
        const { player } = await director.admit(code, p.id);
        store.directorPlayers = { ...store.directorPlayers, [player.id]: player };
    } catch (e) {
        fail(e, `admit ${p.username}`);
    } finally {
        busyPlayer.value = null;
    }
}
async function kick(p) {
    busyPlayer.value = p.id;
    try {
        const { player } = await director.kick(code, p.id);
        store.directorPlayers = { ...store.directorPlayers, [player.id]: player };
    } catch (e) {
        fail(e, `kick ${p.username}`);
    } finally {
        busyPlayer.value = null;
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-dvh w-full max-w-lg flex-col gap-4 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between">
            <router-link to="/director" class="text-sm text-slate-400">← Sessions</router-link>
            <span class="font-mono text-2xl font-black tracking-[0.3em]">{{ code }}</span>
            <router-link :to="`/director/${code}/analysis`" class="text-sm text-slate-400">Analysis →</router-link>
        </header>

        <p v-if="loading" class="py-10 text-center text-slate-400">Loading…</p>
        <p v-else-if="failure" class="py-10 text-center text-rose-200">{{ failure }}</p>
        <template v-else>
            <Banner v-if="store.connection !== 'connected'" tone="warning" :text="store.connection === 'connecting' ? 'Connecting…' : `Connection ${store.connection}. The game clock keeps running on the server; this panel will catch up.`" @dismiss="() => {}" />
            <Banner v-for="(e, i) in errors" :key="e + i" :text="e" @dismiss="errors.splice(i, 1)" />
            <Banner v-if="store.warnings[0]" tone="warning" :text="store.warnings[0].message" @dismiss="store.warnings = []" />

            <StateLine />

            <section v-if="store.state?.status === 'analysis' || finished" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm">
                <div class="flex justify-between gap-3"><span class="text-slate-400">On screen</span><span class="text-right font-semibold">{{ beatLabel(currentBeat) }}</span></div>
                <div v-if="!finished" class="mt-1 flex justify-between gap-3"><span class="text-slate-400">Next</span><span class="text-right">{{ nextBeat ? beatLabel(nextBeat) : 'Finish (podium is up)' }}</span></div>
            </section>

            <ConfirmButton
                v-if="primary"
                :label="primary.label"
                :confirm="primary.confirm"
                :disabled="primary.disabled"
                :busy="busy === primary.action"
                size="lg"
                @confirmed="run(primary.action, primary.label.toLowerCase())"
            />
            <p v-if="primary?.hint" class="-mt-2 text-center text-xs text-slate-400">{{ primary.hint }}</p>
            <p v-if="finished" class="rounded-2xl bg-slate-900 px-4 py-4 text-center text-slate-300">Game over. <router-link :to="`/director/${code}/analysis`" class="text-sky-300">See the analysis.</router-link></p>

            <div class="grid grid-cols-3 gap-2">
                <ConfirmButton v-if="canPause" label="Pause" tone="neutral" :busy="busy === 'pause'" @confirmed="run('pause')" />
                <ConfirmButton v-else-if="store.state?.paused && primary?.action !== 'resume'" label="Resume" tone="neutral" :busy="busy === 'resume'" @confirmed="run('resume')" />
                <div v-else />
                <ConfirmButton label="Reload screen" tone="neutral" :busy="busy === 'screen/reload'" @confirmed="run('screen/reload', 'reload the screen')" />
                <ConfirmButton v-if="!finished" label="End game" tone="danger" confirm :busy="busy === 'end'" @confirmed="run('end', 'end the game')" />
                <div v-else />
            </div>

            <PlayerList :busy-id="busyPlayer" @admit="admit" @kick="kick" />
        </template>
    </main>
</template>
