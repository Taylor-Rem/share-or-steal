<script setup>
import { computed, onMounted, ref } from 'vue';
import { useDirector } from '../useDirector';
import { beatLabel, describeState, explain } from '../logic';
import Banner from '../components/Banner.vue';

/** `/director/{code}/analysis` — the numbers, as tables, for reading a past session. */
const props = defineProps({ code: { type: String, required: true } });
const director = useDirector();
const code = props.code.toUpperCase();
const data = ref(null);
const error = ref(null);

onMounted(async () => {
    try {
        data.value = await director.analysis(code);
    } catch (e) {
        error.value = explain(e, 'load the analysis');
    }
});

const pct = (v) => (v === null || v === undefined ? '—' : `${Math.round(v * 100)}%`);
const num = (v, d = 0) => (v === null || v === undefined ? '—' : Number(v).toFixed(d));
const columns = [
    ['rank', '#', (s) => s.rank],
    ['player', 'Player', (s) => s.player.username],
    ['total_points', 'Pts', (s) => s.total_points],
    ['archetype', 'Archetype', (s) => s.archetype?.label ?? '—'],
    ['share_rate', 'Share', (s) => pct(s.share_rate)],
    ['opening_move', 'Open', (s) => pct(s.opening_move)],
    ['retaliation', 'Retal.', (s) => pct(s.retaliation)],
    ['forgiveness', 'Forgive', (s) => pct(s.forgiveness)],
    ['betrayals', 'Betray', (s) => s.betrayals],
    ['exploitation', 'Exploit', (s) => `${s.exploitation} (${pct(s.exploitation_rate)})`],
    ['endgame_shift', 'Endgame', (s) => (s.endgame_shift === null ? '—' : `${s.endgame_shift > 0 ? '+' : ''}${Math.round(s.endgame_shift * 100)}%`)],
    ['predictability', 'Predict.', (s) => pct(s.predictability)],
    ['partner_yield', 'Yield', (s) => num(s.partner_yield, 2)],
    ['sucker_count', 'Sucker', (s) => s.sucker_count],
    ['times_stolen_from', 'Stolen', (s) => s.times_stolen_from],
    ['timeouts', 'T/O', (s) => `${s.timeouts}/${s.decisions_count}`],
    ['avg_response_ms', 'Avg ms', (s) => s.avg_response_ms ?? '—'],
];
const stats = computed(() => data.value?.stats ?? []);
</script>

<template>
    <main class="mx-auto flex min-h-dvh w-full max-w-5xl flex-col gap-5 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between">
            <router-link :to="`/director/${code}`" class="text-sm text-slate-400">← Panel</router-link>
            <span class="font-mono text-2xl font-black tracking-[0.3em]">{{ code }}</span>
            <span class="text-sm text-slate-400">{{ data ? describeState(data.state) : '' }}</span>
        </header>

        <Banner v-if="error" :text="error" @dismiss="error = null" />
        <p v-else-if="!data" class="py-10 text-center text-slate-400">Loading…</p>
        <template v-else>
            <p v-if="!stats.length" class="rounded-xl bg-slate-900 px-4 py-6 text-center text-slate-500">No analysis yet. It is computed when the last round ends.</p>

            <section v-if="stats.length">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">Player stats</h2>
                <div class="overflow-x-auto rounded-xl bg-slate-900">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500"><tr><th v-for="[k, h] in columns" :key="k" class="whitespace-nowrap px-3 py-2">{{ h }}</th></tr></thead>
                        <tbody>
                            <tr v-for="s in stats" :key="s.player.id" class="border-t border-slate-800">
                                <td v-for="[k, , f] in columns" :key="k" class="whitespace-nowrap px-3 py-1.5 font-mono tabular-nums" :class="k === 'player' || k === 'archetype' ? 'font-sans' : ''">{{ f(s) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="data.awards.length">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">Awards</h2>
                <div class="overflow-x-auto rounded-xl bg-slate-900">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Award</th><th class="px-3 py-2">Winner</th><th class="px-3 py-2">Value</th><th class="px-3 py-2">Tie-break</th></tr></thead>
                        <tbody>
                            <tr v-for="a in data.awards" :key="a.key + (a.place ?? '')" class="border-t border-slate-800">
                                <td class="whitespace-nowrap px-3 py-1.5">{{ a.label }}<span v-if="a.place" class="text-slate-500"> · {{ a.place }}</span></td>
                                <td class="whitespace-nowrap px-3 py-1.5">{{ a.winner.username }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 font-mono">{{ a.value_label }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-slate-400">{{ a.tie_break ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="data.beats.length">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-slate-400">Beats ({{ data.beats.length }})</h2>
                <ol class="flex flex-col gap-1 text-sm">
                    <li v-for="(b, i) in data.beats" :key="i" class="flex items-center gap-3 rounded-lg bg-slate-900 px-3 py-1.5" :class="i === data.state.analysis_beat ? 'ring-1 ring-violet-400' : ''">
                        <span class="w-6 font-mono text-slate-500">{{ i + 1 }}</span>
                        <span class="flex-1">{{ beatLabel(b) }}</span>
                        <span v-if="b.private" class="text-xs text-slate-500">{{ Object.keys(b.private).length }} card{{ Object.keys(b.private).length === 1 ? '' : 's' }}</span>
                    </li>
                </ol>
            </section>
        </template>
    </main>
</template>
