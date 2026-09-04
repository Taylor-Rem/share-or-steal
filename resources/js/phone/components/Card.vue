<script setup>
import { computed } from 'vue';

/** One `you.card` (CONTRACT.md § 11.3), rendered by type. */
const props = defineProps({ card: { type: Object, required: true } });
const p = computed(() => props.card.payload ?? {});
const pct = (v) => (v === null || v === undefined ? '—' : `${Math.round(v * 100)}%`);
const ordinal = (n) => `${n}${['th', 'st', 'nd', 'rd'][(n % 100 > 10 && n % 100 < 14) || n % 10 > 3 ? 0 : n % 10]}`;

const statRows = computed(() => [
    ['Share rate', pct(p.value.share_rate)],
    ['Opening move', pct(p.value.opening_move)],
    ['Forgiveness', pct(p.value.forgiveness)],
    ['Retaliation', pct(p.value.retaliation)],
    ['Betrayals', p.value.betrayals ?? '—'],
    ['Times stolen from', p.value.times_stolen_from ?? '—'],
    ['Endgame shift', p.value.endgame_shift === null || p.value.endgame_shift === undefined ? '—' : `${p.value.endgame_shift > 0 ? '+' : ''}${Math.round(p.value.endgame_shift * 100)}%`],
    ['Avg. tap', p.value.avg_response_ms === null || p.value.avg_response_ms === undefined ? '—' : `${(p.value.avg_response_ms / 1000).toFixed(1)} s`],
]);
</script>

<template>
    <article :key="card.index" class="sos-pop w-full max-w-sm rounded-3xl border border-slate-700 bg-slate-900 p-6 text-center shadow-2xl">
        <template v-if="card.type === 'archetype_reveal' || card.type === 'archetype_cards'">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">You are</p>
            <h2 class="mt-1 text-4xl font-black text-emerald-300">{{ p.archetype?.label ?? 'Unclassified' }}</h2>
            <p class="mt-2 text-slate-300">{{ p.archetype?.blurb }}</p>
            <p class="mt-4 font-mono text-sm text-slate-400">{{ ordinal(p.rank) }} · {{ p.total_points }} points</p>
            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-left text-sm">
                <template v-for="[label, value] in statRows" :key="label">
                    <dt class="text-slate-400">{{ label }}</dt>
                    <dd class="text-right font-mono text-white">{{ value }}</dd>
                </template>
            </dl>
        </template>

        <template v-else-if="card.type === 'award'">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">You won</p>
            <div class="my-2 text-6xl" aria-hidden="true">🏆</div>
            <h2 class="text-4xl font-black">{{ p.award?.label }}</h2>
            <p class="mt-2 text-slate-300">{{ p.award?.description }}</p>
            <p v-if="p.award?.value_label" class="mt-3 font-mono text-2xl text-amber-200">{{ p.award.value_label }}</p>
            <p v-if="p.award?.tie_break" class="mt-1 text-xs text-slate-500">won on {{ p.award.tie_break === 'coin_flip' ? 'a coin flip' : 'points' }}</p>
        </template>

        <template v-else-if="card.type === 'podium'">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Final</p>
            <h2 class="mt-1 text-6xl font-black">{{ ordinal(p.rank) }}</h2>
            <p class="font-mono text-xl text-slate-200">{{ p.total_points }} points</p>
            <p v-if="p.archetype" class="mt-3 text-lg font-semibold text-emerald-300">{{ p.archetype.label }}</p>
            <ul v-if="p.awards?.length" class="mt-3 flex flex-wrap justify-center gap-2">
                <li v-for="a in p.awards" :key="a.key" class="rounded-full bg-amber-400/15 px-3 py-1 text-sm text-amber-200">🏆 {{ a.label }}</li>
            </ul>
        </template>

        <template v-else-if="card.type === 'comparison'">
            <p class="text-xs font-semibold uppercase tracking-widest text-violet-300">Then and now</p>
            <p class="mt-2 text-lg">{{ p.text }}</p>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-sm">
                <template v-for="key in ['share_rate', 'forgiveness', 'betrayals']" :key="key">
                    <div class="rounded-xl bg-slate-800 p-2">
                        <dt class="text-xs text-slate-400">{{ key.replace('_', ' ') }}</dt>
                        <dd class="font-mono">{{ key === 'betrayals' ? p[key]?.before : pct(p[key]?.before) }} → {{ key === 'betrayals' ? p[key]?.after : pct(p[key]?.after) }}</dd>
                    </div>
                </template>
            </dl>
        </template>

        <template v-else>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">{{ card.type.replace(/_/g, ' ') }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-left text-sm">
                <template v-for="(value, key) in p" :key="key">
                    <dt class="text-slate-400">{{ String(key).replace(/_/g, ' ') }}</dt>
                    <dd class="text-right font-mono text-white">{{ typeof value === 'object' ? JSON.stringify(value) : value }}</dd>
                </template>
            </dl>
        </template>
    </article>
</template>
