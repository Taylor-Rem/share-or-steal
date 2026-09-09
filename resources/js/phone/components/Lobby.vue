<script setup>
import { computed, ref } from 'vue';
import { useGameStore } from '../../shared/stores/game';
import { remember } from '../../shared/device';
import { vibrate, BUZZ } from '../../shared/haptics';
import Avatar from '../../shared/Avatar.vue';
import { AVATAR_COLORS, AVATAR_EMOJI, SWATCHES } from '../../shared/avatars';

const store = useGameStore();

// Something to do while the room fills: pick your look. Every tap is saved at once and
// the chip on the big screen changes with it.
const mine = computed(() => store.me?.avatar ?? null);
const picking = ref(true); // always open on arrival, even with last time's look; "done" closes it, the chip reopens it
const saving = ref(false);
const showPicker = computed(() => picking.value);
async function pick(part, value) {
    const next = { emoji: mine.value?.emoji ?? AVATAR_EMOJI[0], color: mine.value?.color ?? AVATAR_COLORS[0], [part]: value };
    vibrate(BUZZ.tap);
    saving.value = true;
    try {
        await store.setAvatar(next);
        remember('avatar', JSON.stringify(next));
    } catch {
        /* the next tap tries again */
    } finally {
        saving.value = false;
    }
}
const count = computed(() => store.state?.player_count ?? store.players.length);
const anonymous = computed(() => store.state?.mode === 'anonymous');
</script>

<template>
    <main class="flex flex-col items-center gap-6 px-6 py-8 text-center">
        <button type="button" class="sos-pop touch-manipulation rounded-full ring-4 ring-emerald-400/40 transition active:scale-95 motion-reduce:transition-none" aria-label="Change your look" @click="picking = !picking">
            <Avatar :avatar="mine" :name="store.me?.username" :size="6" />
        </button>
        <div>
            <h1 class="text-3xl font-black">You're in{{ store.me ? `, ${store.me.username}` : '' }}</h1>
            <p class="mt-2 text-slate-400">{{ count }} {{ count === 1 ? 'player' : 'players' }} in the room. Waiting for the director to start.</p>
        </div>

        <section v-if="showPicker" class="sos-rise w-full max-w-sm rounded-2xl bg-slate-900 p-4" aria-label="Pick your look">
            <p class="mb-3 flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                <span>{{ mine ? 'Your look · change it if you like' : 'Pick your look while you wait' }}</span>
                <span v-if="saving" class="text-emerald-300">saving…</span>
                <button v-else-if="mine" type="button" class="rounded-lg bg-slate-800 px-3 py-1 text-emerald-300" @click="picking = false">done</button>
            </p>
            <div class="grid grid-cols-8 gap-1.5" role="radiogroup" aria-label="Emoji">
                <button
                    v-for="e in AVATAR_EMOJI"
                    :key="e"
                    type="button"
                    role="radio"
                    :aria-checked="mine?.emoji === e"
                    class="flex h-10 touch-manipulation items-center justify-center rounded-xl text-2xl transition active:scale-90 motion-reduce:transition-none"
                    :class="mine?.emoji === e ? 'bg-slate-700 ring-2 ring-emerald-400' : 'bg-slate-950'"
                    @click="pick('emoji', e)"
                >
                    {{ e }}
                </button>
            </div>
            <div class="mt-2 grid grid-cols-8 gap-1.5" role="radiogroup" aria-label="Colour">
                <button
                    v-for="c in AVATAR_COLORS"
                    :key="c"
                    type="button"
                    role="radio"
                    :aria-checked="mine?.color === c"
                    :aria-label="c"
                    class="h-8 touch-manipulation rounded-full transition active:scale-90 motion-reduce:transition-none"
                    :class="[SWATCHES[c], mine?.color === c ? 'ring-4 ring-white/80' : 'opacity-70']"
                    @click="pick('color', c)"
                />
            </div>
        </section>
        <p v-if="anonymous" class="rounded-xl bg-violet-500/15 px-4 py-3 text-sm text-violet-200">This is an anonymous game. Partners appear as codenames and the screen shows no names.</p>
        <ul v-else-if="store.players.length" class="flex max-w-sm flex-wrap justify-center gap-2">
            <li v-for="p in store.players" :key="p.id" class="sos-pop flex items-center gap-1.5 rounded-full bg-slate-800 py-1 pl-1 pr-3 text-sm" :class="p.id === store.me?.id && 'ring-2 ring-emerald-400'">
                <Avatar :avatar="p.avatar" :name="p.username" :size="1.5" />{{ p.username }}
            </li>
        </ul>
        <p class="mt-auto text-xs text-slate-500">Keep an eye on the big screen.</p>
    </main>
</template>
