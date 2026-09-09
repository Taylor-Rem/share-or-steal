<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '../../shared/stores/game';
import { deviceToken, recall, remember } from '../../shared/device';
import Avatar, { SWATCHES } from '../../shared/Avatar.vue';
import { AVATAR_COLORS, AVATAR_EMOJI } from '../../shared/avatars';
import { useAudio } from '../../shared/audio';
import { vibrate, BUZZ } from '../../shared/haptics';

/**
 * `/` — enter the room code and a name. Remembers both (sos.last_code, sos.username).
 * The join tap is also the audio-unlock gesture.
 */
const router = useRouter();
const route = useRoute();
const store = useGameStore();
const audio = useAudio();

const code = ref('');
const username = ref('');
const error = ref(null);
const busy = ref(false);
const avatar = ref({ emoji: AVATAR_EMOJI[Math.floor(Math.random() * AVATAR_EMOJI.length)], color: AVATAR_COLORS[Math.floor(Math.random() * AVATAR_COLORS.length)] });
audio.use('phone');

onMounted(() => {
    code.value = String(route.query.code ?? recall('last_code') ?? '').toUpperCase();
    username.value = recall('username') ?? '';
    try {
        const saved = JSON.parse(recall('avatar') ?? 'null');
        if (saved && AVATAR_EMOJI.includes(saved.emoji) && AVATAR_COLORS.includes(saved.color)) avatar.value = saved;
    } catch {
        /* keep the random one */
    }
    if (route.query.reason === 'not_joined' && code.value) error.value = `Enter your name to join ${code.value}.`;
});

const cleanCode = computed(() => code.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4));
const canJoin = computed(() => cleanCode.value.length === 4 && username.value.trim().length >= 1 && username.value.trim().length <= 24 && !busy.value);

function onCode(event) {
    code.value = event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4);
}

async function join() {
    if (!canJoin.value) return;
    error.value = null;
    busy.value = true;
    audio.unlock();
    vibrate(BUZZ.tap);
    try {
        const data = await store.join({ code: cleanCode.value, username: username.value.trim(), deviceToken: deviceToken(), avatar: avatar.value });
        remember('last_code', cleanCode.value);
        remember('username', data.player.username);
        remember('avatar', JSON.stringify(avatar.value));
        audio.cue('join');
        router.push(`/play/${cleanCode.value}`);
    } catch (e) {
        error.value = e.message ?? 'Something went wrong.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-dvh flex-col items-center justify-center gap-8 px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))]">
        <header class="text-center">
            <h1 class="text-4xl font-black tracking-tight"><span class="text-emerald-400">Share</span> or <span class="text-rose-400">Steal</span></h1>
            <p class="mt-2 text-slate-400">Enter the code on the screen.</p>
        </header>

        <form class="flex w-full max-w-xs flex-col gap-4" @submit.prevent="join">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Room code</span>
                <input
                    :value="code"
                    inputmode="latin"
                    autocapitalize="characters"
                    autocomplete="off"
                    autocorrect="off"
                    spellcheck="false"
                    maxlength="4"
                    placeholder="ABCD"
                    class="h-16 rounded-2xl border border-slate-700 bg-slate-900 text-center font-mono text-3xl font-bold uppercase tracking-[0.4em] text-white placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none"
                    @input="onCode"
                />
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Your name</span>
                <input
                    v-model="username"
                    type="text"
                    autocomplete="nickname"
                    maxlength="24"
                    placeholder="Jordan"
                    class="h-14 rounded-2xl border border-slate-700 bg-slate-900 px-4 text-center text-xl text-white placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none"
                />
            </label>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 flex w-full items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                    <span>Your look</span>
                    <Avatar :avatar="avatar" :name="username" :size="2.25" />
                </legend>
                <div class="grid grid-cols-8 gap-1.5" role="radiogroup" aria-label="Emoji">
                    <button
                        v-for="e in AVATAR_EMOJI"
                        :key="e"
                        type="button"
                        role="radio"
                        :aria-checked="avatar.emoji === e"
                        class="flex h-10 touch-manipulation items-center justify-center rounded-xl text-2xl transition active:scale-90 motion-reduce:transition-none"
                        :class="avatar.emoji === e ? 'bg-slate-700 ring-2 ring-emerald-400' : 'bg-slate-900'"
                        @click="avatar = { ...avatar, emoji: e }"
                    >
                        {{ e }}
                    </button>
                </div>
                <div class="grid grid-cols-8 gap-1.5" role="radiogroup" aria-label="Colour">
                    <button
                        v-for="c in AVATAR_COLORS"
                        :key="c"
                        type="button"
                        role="radio"
                        :aria-checked="avatar.color === c"
                        :aria-label="c"
                        class="h-8 touch-manipulation rounded-full transition active:scale-90 motion-reduce:transition-none"
                        :class="[SWATCHES[c], avatar.color === c ? 'ring-4 ring-white/80' : 'opacity-70']"
                        @click="avatar = { ...avatar, color: c }"
                    />
                </div>
            </fieldset>

            <p v-if="error" class="rounded-xl border border-rose-500/50 bg-rose-500/10 px-4 py-3 text-center text-sm text-rose-200" role="alert">{{ error }}</p>

            <button
                type="submit"
                :disabled="!canJoin"
                class="h-16 touch-manipulation select-none rounded-2xl bg-emerald-500 text-xl font-bold text-slate-950 transition active:scale-95 disabled:opacity-40 motion-reduce:transition-none"
            >
                {{ busy ? 'Joining…' : 'Tap to join' }}
            </button>
        </form>

        <p class="text-xs text-slate-500">Keep this tab open during the game. If you get bumped, just reopen it.</p>
    </main>
</template>
