<script setup>
import { onMounted, ref } from 'vue';
import { useDirector } from '../useDirector';
import { describeState, explain } from '../logic';
import Banner from '../components/Banner.vue';
import CopyRow from '../components/CopyRow.vue';

/** `/director` — create a session, see the code and URLs, and the history. */
const director = useDirector();
const form = ref({ mode: 'normal', rounds_count: 5, decisions_per_round: 10, fast_mode: false, max_players: 30 });
const created = ref(null);
const sessions = ref([]);
const error = ref(null);
const busy = ref(false);

async function load() {
    try {
        sessions.value = (await director.sessions()).sessions;
    } catch (e) {
        error.value = explain(e, 'load sessions');
    }
}
onMounted(load);

async function create() {
    busy.value = true;
    error.value = null;
    try {
        created.value = await director.create({ ...form.value, rounds_count: Number(form.value.rounds_count), decisions_per_round: Number(form.value.decisions_per_round), max_players: Number(form.value.max_players) });
        await load();
    } catch (e) {
        error.value = explain(e, 'create the session');
    } finally {
        busy.value = false;
    }
}

const when = (iso) => (iso ? new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—');
</script>

<template>
    <main class="mx-auto flex min-h-dvh w-full max-w-lg flex-col gap-6 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between">
            <h1 class="text-2xl font-black">Director</h1>
            <button type="button" class="touch-manipulation text-sm text-slate-400" @click="director.logout()">Lock</button>
        </header>

        <Banner v-if="error" :text="error" @dismiss="error = null" />

        <section v-if="created" class="sos-rise flex flex-col gap-3 rounded-2xl border border-emerald-500/40 bg-emerald-500/5 p-4">
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wider text-emerald-300">Room code</div>
                <div class="font-mono text-6xl font-black tracking-[0.3em]">{{ created.state.code }}</div>
                <div class="text-sm text-slate-400">{{ describeState(created.state) }} · {{ created.state.mode }} · {{ created.state.rounds_count }}×{{ created.state.decisions_per_round }}{{ created.state.fast_mode ? ' · fast' : '' }}</div>
            </div>
            <CopyRow label="Join" :value="created.urls.join" />
            <CopyRow label="Screen" :value="created.urls.screen" />
            <CopyRow label="Play" :value="created.urls.play" />
            <CopyRow label="Director" :value="created.urls.director" />
            <router-link :to="`/director/${created.state.code}`" class="flex h-14 items-center justify-center rounded-2xl bg-emerald-500 text-lg font-bold text-slate-950 active:scale-95">Open control panel</router-link>
        </section>

        <form class="flex flex-col gap-3 rounded-2xl bg-slate-900 p-4" @submit.prevent="create">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">New session</h2>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex h-12 items-center justify-center rounded-xl border text-sm font-semibold" :class="form.mode === 'normal' ? 'border-emerald-400 bg-emerald-500/10' : 'border-slate-700'">
                    <input v-model="form.mode" type="radio" value="normal" class="sr-only" />Normal
                </label>
                <label class="flex h-12 items-center justify-center rounded-xl border text-sm font-semibold" :class="form.mode === 'anonymous' ? 'border-violet-400 bg-violet-500/10' : 'border-slate-700'">
                    <input v-model="form.mode" type="radio" value="anonymous" class="sr-only" />Anonymous
                </label>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <label class="flex flex-col gap-1 text-xs text-slate-400">Rounds<input v-model="form.rounds_count" type="number" min="1" max="20" inputmode="numeric" class="h-12 rounded-xl border border-slate-700 bg-slate-950 px-3 text-center text-lg text-white" /></label>
                <label class="flex flex-col gap-1 text-xs text-slate-400">Decisions<input v-model="form.decisions_per_round" type="number" min="1" max="30" inputmode="numeric" class="h-12 rounded-xl border border-slate-700 bg-slate-950 px-3 text-center text-lg text-white" /></label>
                <label class="flex flex-col gap-1 text-xs text-slate-400">Max players<input v-model="form.max_players" type="number" min="2" max="40" inputmode="numeric" class="h-12 rounded-xl border border-slate-700 bg-slate-950 px-3 text-center text-lg text-white" /></label>
            </div>
            <label class="flex h-12 items-center justify-between rounded-xl border border-slate-700 px-3 text-sm">
                <span>Fast mode <span class="text-slate-500">(1 s clocks, for testing)</span></span>
                <input v-model="form.fast_mode" type="checkbox" class="h-6 w-6 accent-emerald-500" />
            </label>
            <button type="submit" :disabled="busy" class="h-14 touch-manipulation rounded-2xl bg-emerald-500 text-lg font-bold text-slate-950 active:scale-95 disabled:opacity-40">{{ busy ? 'Creating…' : 'Create session' }}</button>
        </form>

        <section>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">Sessions</h2>
            <p v-if="!sessions.length" class="rounded-xl bg-slate-900 px-4 py-6 text-center text-slate-500">None yet.</p>
            <ul v-else class="flex flex-col gap-1.5">
                <li v-for="s in sessions" :key="s.code" class="flex items-center gap-3 rounded-xl bg-slate-900 px-3 py-2">
                    <router-link :to="`/director/${s.code}`" class="min-w-0 flex-1">
                        <div class="flex items-center gap-2"><span class="font-mono text-lg font-bold tracking-widest">{{ s.code }}</span><span class="text-xs text-slate-400">{{ s.status.replace('_', ' ') }}{{ s.paused ? ' · paused' : '' }}</span></div>
                        <div class="text-xs text-slate-500">{{ s.player_count }} players · {{ s.mode }}{{ s.fast_mode ? ' · fast' : '' }} · {{ when(s.created_at) }}</div>
                    </router-link>
                    <router-link v-if="s.status === 'analysis' || s.status === 'finished'" :to="`/director/${s.code}/analysis`" class="h-10 shrink-0 rounded-lg bg-slate-800 px-3 text-sm font-semibold leading-10">Analysis</router-link>
                </li>
            </ul>
        </section>
    </main>
</template>
